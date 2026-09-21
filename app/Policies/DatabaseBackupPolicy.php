<?php

namespace App\Policies;

use App\Enums\BackupStatus;
use App\Models\DatabaseBackup;
use App\Models\User;
use App\Support\Access\Access;

class DatabaseBackupPolicy
{
    public function viewAny(User $actor): bool
    {
        return Access::userCan($actor, 'admin.backups_manage');
    }

    public function view(User $actor, DatabaseBackup $backup): bool
    {
        return Access::userCan($actor, 'admin.backups_manage');
    }

    public function create(User $actor): bool
    {
        return Access::userCan($actor, 'admin.backups_manage');
    }

    public function download(User $actor, DatabaseBackup $backup): bool
    {
        return Access::userCan($actor, 'admin.backups_manage')
            && $backup->status->isDownloadable()
            && $backup->fileExists();
    }

    public function downloadFiles(User $actor, DatabaseBackup $backup): bool
    {
        return Access::userCan($actor, 'admin.backups_manage')
            && $backup->status->isDownloadable()
            && $backup->filesArchiveExists();
    }

    public function verify(User $actor, DatabaseBackup $backup): bool
    {
        return Access::userCan($actor, 'admin.backups_manage')
            && $backup->status->isDownloadable()
            && $backup->fileExists();
    }

    public function testRestore(User $actor, DatabaseBackup $backup): bool
    {
        return Access::userCan($actor, 'admin.backups_manage')
            && $backup->status->isRestorable()
            && $backup->fileExists();
    }

    public function restore(User $actor, DatabaseBackup $backup): bool
    {
        return Access::userCan($actor, 'admin.backups_manage')
            && $backup->status->isRestorable()
            && $backup->fileExists();
    }

    public function restoreFiles(User $actor, DatabaseBackup $backup): bool
    {
        return Access::userCan($actor, 'admin.backups_manage')
            && $backup->status->isRestorable()
            && $backup->filesArchiveExists();
    }

    public function delete(User $actor, DatabaseBackup $backup): bool
    {
        // Completed/verified backups are retained by policy; only failed catalog rows may be dismissed.
        return Access::userCan($actor, 'admin.backups_manage')
            && $backup->status === BackupStatus::Failed;
    }
}
