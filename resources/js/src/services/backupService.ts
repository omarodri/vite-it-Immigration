import api from '@/services/api';
import type { BackupLog } from '@/types/backup';

const backupService = {
    async runBackup(tenantId: number): Promise<{ message: string }> {
        const response = await api.post(`/admin/tenants/${tenantId}/backup`);
        return response.data;
    },

    async listBackups(tenantId: number): Promise<BackupLog[]> {
        const response = await api.get(`/admin/tenants/${tenantId}/backups`);
        return response.data.data;
    },

    async downloadBackup(logId: number): Promise<Blob> {
        const response = await api.get(`/admin/backups/${logId}/download`, {
            responseType: 'blob',
        });
        return response.data;
    },

    async deleteBackup(logId: number): Promise<void> {
        await api.delete(`/admin/backups/${logId}`);
    },
};

export default backupService;
