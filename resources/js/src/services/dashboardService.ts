import api from '@/services/api';
import type { DashboardData } from '@/types/dashboard';

const dashboardService = {
    async getDashboard(): Promise<DashboardData> {
        const response = await api.get<{ data: DashboardData }>('/dashboard');
        return response.data.data;
    },
};

export default dashboardService;
