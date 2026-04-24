<?php

declare(strict_types=1);

namespace App\Controllers;

use PDO;
use App\Models\User;
use App\Models\EmailVerification;
use App\Models\PasswordReset;
use App\Helpers\MailHelper;
use App\Helpers\JwtHelper;

class AuthController
{
  private const VERIFY_TOKEN_TTL_SECONDS = 3600;
  private const RESET_TOKEN_TTL_SECONDS = 3600;

  private PDO $db;

  public function __construct(PDO $db)
  {
    $this->db = $db;
  }

  public function login(): void
  {
    try {
      $input = self::getJson();
      $identifier = $input['loginInput'] ?? $input['email'] ?? $input['username'] ?? null;
      $password = $input['password'] ?? null;
      $otp = preg_replace('/\D+/', '', (string) ($input['otp'] ?? ''));
      $otp = is_string($otp) ? $otp : '';
      
      // Debug logging
      error_log('Login attempt: identifier=' . (string) $identifier . ', has_password=' . (!empty($password) ? 'yes' : 'no') . ', has_otp=' . (!empty($otp) ? 'yes' : 'no'));
      
      if (!$identifier || !$password) {
        error_log('Login failed: missing identifier or password. identifier=' . var_export($identifier, true) . ', password=' . var_export($password, true));
        self::json(400, ['message' => 'Please provide username/email and password']);
        return;
      }
      $userModel = new User($this->db);
      $user = $userModel->findByUsernameOrEmail((string) $identifier);
      if (!$user || !password_verify((string) $password, (string) ($user->password ?? ''))) {
        error_log('Login failed: invalid credentials for identifier=' . (string) $identifier . ', user_found=' . ($user ? 'yes' : 'no'));
        self::json(400, ['message' => 'Invalid credentials']);
        return;
      }
      
      error_log('Login successful: user_id=' . $user->user_id . ', username=' . $user->username);

      if ($otp === '') {
        $sendResult = $this->sendVerificationOtp((int) $user->user_id, (string) ($user->email ?? ''), (string) ($user->username ?? ''));
        if (!$sendResult['sent']) {
          $payload = ['message' => 'Failed to send OTP. Please try again later.'];
          if (strtolower((string) (getenv('APP_ENV') ?: 'production')) !== 'production') {
            $payload['mail_error'] = $sendResult['error'];
          }
          self::json(500, $payload);
          return;
        }

        self::json(202, [
          'code' => 'LOGIN_OTP_REQUIRED',
          'message' => 'OTP sent. Enter the 6-digit code to continue login.',
          'requires_otp' => true,
          'email' => $user->email ?? '',
        ]);
        return;
      }

      if (!preg_match('/^\d{6}$/', $otp)) {
        self::json(400, ['message' => 'Please enter a valid 6-digit OTP']);
        return;
      }

      $verificationModel = new EmailVerification($this->db);
      $otpHash = hash('sha256', $otp);
      $record = $verificationModel->consumeValidCodeByUserId((int) $user->user_id, $otpHash);
      if (!$record) {
        self::json(400, ['message' => 'Invalid or expired OTP']);
        return;
      }

      if ((int) ($user->email_verified ?? 0) !== 1) {
        $userModel->markEmailVerified((int) $user->user_id);
      }
      $verificationModel->invalidatePendingByUserId((int) $user->user_id);

      $secret = getenv('JWT_SECRET') ?: 'secret';
      $token = JwtHelper::encode(
        ['id' => $user->user_id, 'username' => $user->username],
        $secret
      );
      $payload = ['user_id' => $user->user_id, 'username' => $user->username, 'email' => $user->email ?? ''];
      if (!empty($user->profile_photo)) $payload['profile_photo'] = $user->profile_photo;
      self::json(200, ['message' => 'Login successful', 'token' => $token, 'user' => $payload]);
    } catch (\Throwable $e) {
      http_response_code(500);
      header('Content-Type: application/json; charset=utf-8');
      echo json_encode([
        'message' => 'Login failed',
        'error' => $e->getMessage(),
        'file' => $e->getFile() . ':' . $e->getLine(),
      ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }
  }

  public function register(): void
  {
    $input = self::getJson();
    $username = trim((string) ($input['username'] ?? ''));
    $email = trim((string) ($input['email'] ?? ''));
    $password = (string) ($input['password'] ?? '');
    if (!$username || !$email || !$password) {
      self::json(400, ['message' => 'Please fill in all fields']);
      return;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      self::json(400, ['message' => 'Please provide a valid email address']);
      return;
    }
    if (!$this->isStrongPassword((string) $password)) {
      self::json(400, ['message' => 'Password must be at least 16 characters and include at least one uppercase letter, one lowercase letter, one number, and one special character.']);
      return;
    }
    $userModel = new User($this->db);
    if ($userModel->findByUsername($username)) {
      self::json(409, ['message' => 'Username is already taken']);
      return;
    }
    if ($userModel->findByEmail($email)) {
      self::json(409, ['message' => 'Email is already registered']);
      return;
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);
    try {
      $id = $userModel->create($username, $email, $hash);
    } catch (\Throwable $e) {
      $message = (string) $e->getMessage();
      if (
        stripos($message, 'Duplicate entry') !== false ||
        stripos($message, 'UNIQUE constraint failed') !== false ||
        stripos($message, 'SQLSTATE[23000]') !== false
      ) {
        self::json(409, ['message' => 'Username or email is already in use']);
        return;
      }
      throw $e;
    }

    $sendResult = $this->sendVerificationOtp((int) $id, $email, $username);
    $message = $sendResult['sent']
      ? 'Account created. We sent a 6-digit OTP to your email.'
      : 'Account created, but verification OTP email could not be sent. Please use resend OTP after configuring mail settings.';

    $payload = [
      'message' => $message,
      'requires_verification' => true,
      'requires_otp' => true,
      'email_sent' => $sendResult['sent'],
      'email' => $email,
    ];

    if (!$sendResult['sent'] && strtolower((string) (getenv('APP_ENV') ?: 'production')) !== 'production') {
      $payload['mail_error'] = $sendResult['error'];
    }

    self::json(201, [
      ...$payload,
    ]);
  }

  public function resendVerification(): void
  {
    $input = self::getJson();
    $email = trim((string) ($input['email'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
      self::json(400, ['message' => 'Please provide a valid email address']);
      return;
    }

    $userModel = new User($this->db);
    $user = $userModel->findByEmail($email);
    if (!$user) {
      self::json(200, ['message' => 'If the account exists, an OTP has been sent.']);
      return;
    }

    $sendResult = $this->sendVerificationOtp((int) $user->user_id, (string) $user->email, (string) ($user->username ?? ''));
    if (!$sendResult['sent']) {
      $payload = ['message' => 'Failed to send OTP. Please try again later.'];
      if (strtolower((string) (getenv('APP_ENV') ?: 'production')) !== 'production') {
        $payload['mail_error'] = $sendResult['error'];
      }
      self::json(500, $payload);
      return;
    }

    self::json(200, ['message' => 'OTP sent. Please check your inbox.']);
  }

  public function verifyEmailOtp(): void
  {
    $input = self::getJson();
    $email = trim((string) ($input['email'] ?? ''));
    $otp = preg_replace('/\D+/', '', (string) ($input['otp'] ?? ''));
    $otp = is_string($otp) ? $otp : '';

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
      self::json(400, ['message' => 'Please provide a valid email address']);
      return;
    }

    if (!preg_match('/^\d{6}$/', $otp)) {
      self::json(400, ['message' => 'Please enter a valid 6-digit OTP']);
      return;
    }

    $userModel = new User($this->db);
    $user = $userModel->findByEmail($email);
    if (!$user) {
      self::json(400, ['message' => 'Invalid OTP or email']);
      return;
    }

    if ((int) ($user->email_verified ?? 0) === 1) {
      self::json(200, ['message' => 'Email already verified. You can log in now.']);
      return;
    }

    $verificationModel = new EmailVerification($this->db);
    $otpHash = hash('sha256', $otp);
    $record = $verificationModel->consumeValidCodeByUserId((int) $user->user_id, $otpHash);
    if (!$record) {
      self::json(400, ['message' => 'Invalid or expired OTP']);
      return;
    }

    $userModel->markEmailVerified((int) $user->user_id);
    $verificationModel->invalidatePendingByUserId((int) $user->user_id);

    self::json(200, ['message' => 'Email verified successfully. You can log in now.']);
  }

  public function verifyEmail(): void
  {
    $token = trim((string) ($_GET['token'] ?? ''));
    if ($token === '') {
      if ($this->wantsJsonResponse()) {
        self::json(400, ['message' => 'Verification token is required']);
        return;
      }
      $this->redirectWithVerificationStatus(false, 'missing_token');
      return;
    }

    $tokenHash = hash('sha256', $token);
    $verificationModel = new EmailVerification($this->db);
    $record = $verificationModel->consumeValidToken($tokenHash);
    if (!$record) {
      if ($this->wantsJsonResponse()) {
        self::json(400, ['message' => 'Invalid or expired verification link']);
        return;
      }
      $this->redirectWithVerificationStatus(false, 'invalid_or_expired');
      return;
    }

    $userModel = new User($this->db);
    $userModel->markEmailVerified((int) $record->user_id);
    $verificationModel->invalidatePendingByUserId((int) $record->user_id);

    if ($this->wantsJsonResponse()) {
      self::json(200, ['message' => 'Email verified successfully']);
      return;
    }

    $this->redirectWithVerificationStatus(true, 'verified');
  }

  public function forgotPassword(): void
  {
    $input = self::getJson();
    $email = trim((string) ($input['email'] ?? ''));

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
      self::json(400, ['message' => 'Please provide a valid email address']);
      return;
    }

    $userModel = new User($this->db);
    $user = $userModel->findByEmail($email);
    if (!$user) {
      self::json(200, ['message' => 'If the account exists, a reset OTP has been sent to your email.']);
      return;
    }

    $resetToken = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $resetToken);
    $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $otpHash = hash('sha256', $otp);

    $passwordResetModel = new PasswordReset($this->db);
    $passwordResetModel->invalidatePendingByUserId((int) $user->user_id);
    $passwordResetModel->createToken((int) $user->user_id, $tokenHash, $otpHash, self::RESET_TOKEN_TTL_SECONDS);

    $expiryMinutes = (int) floor(self::RESET_TOKEN_TTL_SECONDS / 60);
    $mailResult = MailHelper::sendPasswordResetEmail(
      (string) ($user->email ?? ''),
      (string) ($user->username ?? ''),
      $otp,
      $expiryMinutes
    );

    if (!((bool) ($mailResult['ok'] ?? false))) {
      $payload = ['message' => 'Failed to send reset OTP. Please try again later.'];
      if (strtolower((string) (getenv('APP_ENV') ?: 'production')) !== 'production') {
        $payload['mail_error'] = (string) ($mailResult['error'] ?? '');
      }
      self::json(500, $payload);
      return;
    }

    self::json(200, ['message' => 'If the account exists, a reset OTP has been sent to your email.']);
  }

  public function validateResetPasswordToken(): void
  {
    $token = trim((string) ($_GET['token'] ?? ''));
    if ($token === '') {
      self::json(400, ['message' => 'Reset token is required']);
      return;
    }

    $tokenHash = hash('sha256', $token);
    $passwordResetModel = new PasswordReset($this->db);
    $record = $passwordResetModel->findValidToken($tokenHash);
    if (!$record) {
      self::json(400, ['message' => 'Reset link is invalid or expired']);
      return;
    }

    self::json(200, ['valid' => true, 'requires_otp' => true]);
  }

  public function resetPassword(): void
  {
    $input = self::getJson();
    $email = trim((string) ($input['email'] ?? ''));
    $otp = preg_replace('/\D+/', '', (string) ($input['otp'] ?? ''));
    $otp = is_string($otp) ? $otp : '';
    $newPassword = (string) ($input['password'] ?? '');

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
      self::json(400, ['message' => 'Please provide a valid email address']);
      return;
    }

    if (!preg_match('/^\d{6}$/', $otp)) {
      self::json(400, ['message' => 'Please enter a valid 6-digit OTP']);
      return;
    }

    if (!$this->isStrongPassword($newPassword)) {
      self::json(400, ['message' => 'Password must be at least 16 characters and include at least one uppercase letter, one lowercase letter, one number, and one special character.']);
      return;
    }

    $userModel = new User($this->db);
    $user = $userModel->findByEmail($email);
    if (!$user) {
      self::json(400, ['message' => 'Invalid OTP or email']);
      return;
    }

    $passwordResetModel = new PasswordReset($this->db);
    $record = $passwordResetModel->consumeValidOtpByUserId((int) $user->user_id, hash('sha256', $otp));
    if (!$record) {
      self::json(400, ['message' => 'Invalid or expired OTP']);
      return;
    }

    $userId = (int) ($record->user_id ?? 0);
    if ($userId <= 0) {
      self::json(400, ['message' => 'Invalid or expired OTP']);
      return;
    }

    $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
    $userModel->updatePassword($userId, $hashedPassword);
    $passwordResetModel->invalidatePendingByUserId($userId);

    self::json(200, ['message' => 'Password reset successful. You can now sign in.']);
  }


  private static function getJson(): array
  {
    $raw = file_get_contents('php://input');
    $dec = json_decode($raw, true);
    return is_array($dec) ? $dec : [];
  }

  private static function json(int $code, array $data): void
  {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
  }

  private function sendVerificationOtp(int $userId, string $email, string $username): array
  {
    $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $tokenHash = hash('sha256', $otp);

    $verificationModel = new EmailVerification($this->db);
    $verificationModel->createToken($userId, $tokenHash, self::VERIFY_TOKEN_TTL_SECONDS);

    $expiryMinutes = (int) floor(self::VERIFY_TOKEN_TTL_SECONDS / 60);
    $mailResult = MailHelper::sendVerificationOtpEmail($email, $username, $otp, $expiryMinutes);

    return [
      'sent' => (bool) ($mailResult['ok'] ?? false),
      'error' => (string) ($mailResult['error'] ?? ''),
    ];
  }

  private function buildVerifyUrl(string $token): string
  {
    $base = rtrim((string) (getenv('BASE_URL') ?: $this->baseUrl()), '/');
    if (strpos($base, '?api=') !== false) {
      [$basePath, $baseQuery] = array_pad(explode('?', $base, 2), 2, '');
      parse_str($baseQuery, $query);
      $apiPath = isset($query['api']) ? trim((string) $query['api']) : '';
      $query['api'] = rtrim($apiPath, '/') . '/auth/verify-email';
      $query['token'] = $token;
      return $basePath . '?' . http_build_query($query);
    }
    return $base . '/api/auth/verify-email?token=' . urlencode($token);
  }

  private function buildResetPasswordUrl(string $token): string
  {
    return $this->frontendBaseUrl() . '/#/reset-password?token=' . urlencode($token);
  }

  private function frontendBaseUrl(): string
  {
    $frontendUrl = trim((string) (getenv('FRONTEND_URL') ?: ''));
    if ($frontendUrl !== '') {
      return rtrim($frontendUrl, '/');
    }
    $base = rtrim((string) (getenv('BASE_URL') ?: $this->baseUrl()), '/');
    if (strpos($base, '?') !== false) {
      $base = explode('?', $base, 2)[0];
    }
    return rtrim($base, '/');
  }

  private function redirectWithVerificationStatus(bool $success, string $reason): void
  {
    $url = $this->frontendBaseUrl() . '/#/?emailVerified=' . ($success ? '1' : '0');
    if ($reason !== '') {
      $url .= '&reason=' . urlencode($reason);
    }
    header('Location: ' . $url, true, 302);
    exit;
  }

  private function wantsJsonResponse(): bool
  {
    $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
    $xrw = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
    return strpos($accept, 'application/json') !== false || $xrw === 'xmlhttprequest';
  }

  private function baseUrl(): string
  {
    $url = getenv('BASE_URL');
    if ($url) return rtrim($url, '/');
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($host !== '') {
      return $scheme . '://' . $host;
    }
    return '';
  }

  private function isStrongPassword(string $password): bool
  {
    if (strlen($password) < 16) {
      return false;
    }

    $hasUppercase = preg_match('/[A-Z]/', $password) === 1;
    $hasLowercase = preg_match('/[a-z]/', $password) === 1;
    $hasNumber = preg_match('/[0-9]/', $password) === 1;
    $hasSpecial = preg_match('/[^A-Za-z0-9]/', $password) === 1;

    return $hasUppercase && $hasLowercase && $hasNumber && $hasSpecial;
  }
}
