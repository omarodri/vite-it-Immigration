import api from '@/services/api';
import type { DashboardData, PaginationMeta } from '@/types/dashboard';
import type { DashboardTask } from '@/types/dashboard';

const dashboardService = {
    async getDashboard(): Promise<DashboardData> {
        const response = await api.get<{ data: DashboardData }>('/dashboard');
        return response.data.data;
    },

    async getAssignedTasks(page: number, perPage = 10): Promise<{ data: DashboardTask[]; meta: PaginationMeta; links: Record<string, string | null> }> {
        const response = await api.get('/dashboard/assigned-tasks', {
            params: { page, per_page: perPage },
        });
        return response.data;
    },
};

export default dashboardService;
