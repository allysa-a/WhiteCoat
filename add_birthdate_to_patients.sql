-- Add birthdate column to patients table
ALTER TABLE patients ADD COLUMN birthdate DATE NULL AFTER age;