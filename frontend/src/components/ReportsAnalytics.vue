<template>
  <div class="reports-analytics-card">
    <div class="card-header">
      <div>
        <h3 class="card-title">Patient Overview</h3>
        <p class="card-subtitle">by Patient Type</p>
      </div>
      <button class="menu-button" @click="showMenu = !showMenu">
        <font-awesome-icon :icon="['fas', 'ellipsis']" />
      </button>
    </div>

    <div class="chart-container">
      <div class="donut-chart-wrapper">
        <template v-if="hasAnalytics">
          <svg :width="chartSize" :height="chartSize" class="donut-chart">
            <g v-for="(segment, index) in chartSegments" :key="index">
              <path
                :d="getArcPath(segment)"
                :fill="segment.color"
                class="chart-segment"
              />
            </g>
          </svg>
        </template>
        <template v-else>
          <div class="empty-chart-state">
          </div>
        </template>

        <div class="chart-center">
          <div class="center-label">Overall</div>
          <div class="center-value">{{ total }}</div>
          <div class="center-range">
            <select v-model="selectedRange" @change="fetchAnalytics" class="range-select">
              <option value="weekly">This Week</option>
              <option value="monthly">This Month</option>
              <option value="annually">This Year</option>
            </select>
          </div>
        </div>
      </div>

      <div class="chart-legend">
        <div
          v-for="(item, index) in breakdown"
          :key="index"
          class="legend-item"
        >
          <div class="legend-color" :style="{ backgroundColor: item.color }"></div>
          <div class="legend-text">
            <span class="legend-label">{{ item.patient_type }}</span>
            <span class="legend-percentage">{{ item.percentage }}%</span>
          </div>
        </div>
      </div>
    </div>

    <div class="filters-section">
      <label class="filter-label">Filter by Patient Type:</label>
      <select v-model="selectedPatientType" @change="fetchAnalytics" class="filter-select">
        <option value="">All Types</option>
        <option value="Non-Teaching">Non-Teaching</option>
        <option value="Teaching">Teaching</option>
        <option value="Learner">Learner</option>
        <option value="School Head">School Head</option>
        <option value="Related-Teaching">Related-Teaching</option>
      </select>
    </div>

    <div class="actions-section">
      <button class="export-button" @click="exportToExcel">
        <font-awesome-icon :icon="['fas', 'file-excel']" class="mr-2" />
        Download as Excel
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import axios from 'axios';

const props = defineProps<{
  refreshKey?: number;
}>();

const API_URL = ((import.meta as any).env?.VITE_API_BASE_URL || '').replace(/\/+$/, '') || window.location.origin;

const selectedRange = ref<'weekly' | 'monthly' | 'annually'>('annually');
const selectedPatientType = ref<string>('');
const total = ref(0);
const breakdown = ref<Array<{ patient_type: string; count: number; percentage: number; color: string }>>([]);
const loading = ref(false);
const showMenu = ref(false);

const chartSize = 200;
const centerX = chartSize / 2;
const centerY = chartSize / 2;
const radius = 70;
const innerRadius = 50;
const circumference = 2 * Math.PI * radius;

// Colors for each patient type
const colors = {
  'Non-Teaching': '#1e40af',
  'Teaching': '#3b82f6',
  'Learner': '#93c5fd',
  'School Head': '#60a5fa',
  'Related-Teaching': '#2563eb',
  'Unknown': '#9ca3af',
};

const hasAnalytics = computed(() => total.value > 0 && breakdown.value.length > 0);

const chartSegments = computed(() => {
  if (breakdown.value.length === 0 || total.value === 0) return [];
  
  let currentAngle = -90; // Start from top
  return breakdown.value
    .filter((item) => item.count > 0)
    .map((item) => {
      const percentage = item.percentage / 100;
      const angle = percentage * 360;
      const startAngle = currentAngle;
      const endAngle = currentAngle + angle;
      
      currentAngle += angle;
      
      return {
        ...item,
        startAngle,
        endAngle,
      };
    });
});

const getArcPath = (segment: { startAngle: number; endAngle: number }) => {
  const startRad = (segment.startAngle * Math.PI) / 180;
  const endRad = (segment.endAngle * Math.PI) / 180;
  
  const x1 = centerX + radius * Math.cos(startRad);
  const y1 = centerY + radius * Math.sin(startRad);
  const x2 = centerX + radius * Math.cos(endRad);
  const y2 = centerY + radius * Math.sin(endRad);
  
  const x3 = centerX + innerRadius * Math.cos(endRad);
  const y3 = centerY + innerRadius * Math.sin(endRad);
  const x4 = centerX + innerRadius * Math.cos(startRad);
  const y4 = centerY + innerRadius * Math.sin(startRad);
  
  const largeArc = segment.endAngle - segment.startAngle > 180 ? 1 : 0;
  
  return [
    `M ${x1} ${y1}`,
    `A ${radius} ${radius} 0 ${largeArc} 1 ${x2} ${y2}`,
    `L ${x3} ${y3}`,
    `A ${innerRadius} ${innerRadius} 0 ${largeArc} 0 ${x4} ${y4}`,
    'Z',
  ].join(' ');
};

const fetchAnalytics = async () => {
  loading.value = true;
  try {
    const stored = localStorage.getItem('whitecoat_user');
    if (!stored) return;
    
    const user = JSON.parse(stored) as { user_id?: number };
    if (!user.user_id) return;

    const params: Record<string, string> = {
      user_id: String(user.user_id),
      range: selectedRange.value,
    };
    
    if (selectedPatientType.value) {
      params.patient_type = selectedPatientType.value;
    }

    const res = await axios.get(`${API_URL}/api/doctor/analytics`, { params });
    total.value = res.data.total || 0;
    
    // Add colors to breakdown
    breakdown.value = (res.data.breakdown || []).map((item: { patient_type: string; count: number; percentage: number }) => ({
      ...item,
      color: colors[item.patient_type as keyof typeof colors] || colors.Unknown,
    }));
  } catch (err) {
    console.error('Fetch analytics error:', err);
    total.value = 0;
    breakdown.value = [];
  } finally {
    loading.value = false;
  }
};

const exportToExcel = async () => {
  try {
    const stored = localStorage.getItem('whitecoat_user');
    if (!stored) return;
    
    const user = JSON.parse(stored) as { user_id?: number };
    if (!user.user_id) return;

    const params: Record<string, string> = {
      user_id: String(user.user_id),
      range: selectedRange.value,
    };
    
    if (selectedPatientType.value) {
      params.patient_type = selectedPatientType.value;
    }

    const res = await axios.get(`${API_URL}/api/doctor/analytics/export`, {
      params,
      responseType: 'blob',
    });

    const contentType = (res.headers['content-type'] as string | undefined) || 'text/csv;charset=utf-8';
    const blob = new Blob([res.data], { type: contentType });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    const disposition = res.headers['content-disposition'] as string | undefined;
    const backendFilename = disposition
      ? ((disposition.split('filename=')[1] || '').replace(/"/g, '').trim())
      : '';
    link.download = backendFilename || `analytics_${selectedRange.value}_${selectedPatientType.value || 'all'}_${Date.now()}.csv`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(url);
  } catch (err) {
    console.error('Export error:', err);
    alert('Failed to export analytics. Please try again.');
  }
};

onMounted(() => {
  fetchAnalytics();
});

watch(() => props.refreshKey, () => {
  fetchAnalytics();
});
</script>

<style scoped>
.reports-analytics-card {
  background: white;
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
  margin-top: 20px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 20px;
}

.card-title {
  font-size: 1.25rem;
  font-weight: 700;
  color: #111;
  margin: 0 0 4px 0;
}

.card-subtitle {
  font-size: 0.875rem;
  color: #6b7280;
  margin: 0;
}

.menu-button {
  background: none;
  border: none;
  cursor: pointer;
  color: #6b7280;
  padding: 4px 8px;
  font-size: 1.25rem;
}

.chart-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 20px;
  margin-bottom: 20px;
}

.donut-chart-wrapper {
  position: relative;
  width: 200px;
  height: 200px;
}

.donut-chart {
  transform: rotate(0deg);
}

.empty-chart-state {
  width: 100%;
  height: 200px;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  text-align: center;
  color: #6b7280;
  font-size: 0.9rem;
  padding: 16px;
}

.empty-chart-state p {
  margin: 4px 0;
}


.chart-segment:hover {
  opacity: 0.8;
}

.chart-center {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  text-align: center;
  pointer-events: none;
}

.center-label {
  font-size: 0.75rem;
  color: #6b7280;
  margin-bottom: 4px;
}

.center-value {
  font-size: 2rem;
  font-weight: 700;
  color: #111;
  margin-bottom: 8px;
}

.center-range {
  pointer-events: all;
}

.range-select {
  background: none;
  border: none;
  font-size: 0.75rem;
  color: #6b7280;
  cursor: pointer;
  padding: 2px 4px;
  appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%236b7280' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right center;
  padding-right: 16px;
}

.chart-legend {
  display: flex;
  flex-direction: column;
  gap: 12px;
  width: 100%;
}

.legend-item {
  display: flex;
  align-items: center;
  gap: 12px;
}

.legend-color {
  width: 16px;
  height: 16px;
  border-radius: 2px;
  flex-shrink: 0;
}

.legend-text {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex: 1;
}

.legend-label {
  font-size: 0.875rem;
  color: #374151;
}

.legend-percentage {
  font-size: 0.875rem;
  font-weight: 600;
  color: #111;
}

.filters-section {
  margin-bottom: 16px;
  padding-top: 16px;
  border-top: 1px solid #e5e7eb;
}

.filter-label {
  display: block;
  font-size: 0.875rem;
  font-weight: 600;
  color: #374151;
  margin-bottom: 8px;
}

.filter-select {
  width: 100%;
  padding: 8px 12px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 0.875rem;
  background: white;
  color: #111;
  cursor: pointer;
}

.actions-section {
  padding-top: 16px;
  border-top: 1px solid #e5e7eb;
}

.export-button {
  width: 100%;
  padding: 10px 16px;
  background: #023E8A;
  color: white;
  border: none;
  border-radius: 6px;
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  transition: background 0.2s;
}

.export-button:hover {
  background: #022d6b;
}

.mr-2 {
  margin-right: 0.5rem;
}
</style>
