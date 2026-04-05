export type BackupStatus = 'pending' | 'running' | 'completed' | 'failed';
export type BackupTriggerSource = 'ui' | 'cron' | 'api' | 'cli';

export interface BackupLog {
    id: number;
    status: BackupStatus;
    trigger_source: BackupTriggerSource;
    filename: string | null;
    file_size_mb: number;
    row_count: number | null;
    duration_s: number | null;
    error_message: string | null;
    started_at: string | null;
    completed_at: string | null;
    created_at: string;
}
