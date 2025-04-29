<?php
namespace App\Console\Commands;

use App\Models\ApiCache;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ClearApiCache extends Command
{
    /**
     * Nama signature command.
     *
     * @var string
     */
    protected $signature = 'cache:api-clear
                            {--all : Hapus semua cache API}
                            {--expired : Hapus hanya cache API yang kadaluwarsa}
                            {--endpoint= : Hapus cache untuk endpoint tertentu}
                            {--pattern= : Hapus cache untuk pattern endpoint tertentu}
                            {--maintenance : Jalankan maintenance cache lengkap}';

    /**
     * Deskripsi command.
     *
     * @var string
     */
    protected $description = 'Membersihkan cache API untuk meningkatkan performa';

    /**
     * Eksekusi command.
     */
    public function handle()
    {
        if ($this->option('all')) {
            $count = ApiCache::count();
            ApiCache::clearAll();
            $this->info("Berhasil menghapus semua cache API ({$count} item).");
            return;
        }

        if ($this->option('expired')) {
            $count = ApiCache::clearExpired();
            $this->info("Berhasil menghapus {$count} cache API yang kadaluwarsa.");
            return;
        }

        if ($endpoint = $this->option('endpoint')) {
            $count = ApiCache::clearEndpoint($endpoint);
            $this->info("Berhasil menghapus {$count} cache untuk endpoint '{$endpoint}'.");
            return;
        }

        if ($pattern = $this->option('pattern')) {
            $count = ApiCache::clearByPattern("%{$pattern}%");
            $this->info("Berhasil menghapus {$count} cache untuk pattern '{$pattern}'.");
            return;
        }

        if ($this->option('maintenance')) {
            $result = ApiCache::performMaintenance();

            if (isset($result['error'])) {
                $this->error("Maintenance gagal: {$result['error']}");
                return;
            }

            $this->info("Maintenance cache API selesai:");
            $this->line("- Cache kadaluwarsa dihapus: {$result['expired_removed']}");
            $this->line("- Cache lama dihapus: {$result['old_removed']}");
            $this->line("- Cache berlebih dihapus: {$result['excess_removed']}");

            // Sekalian bersihkan Laravel cache
            Cache::flush();
            $this->line("- Laravel cache dibersihkan");

            return;
        }

        // Default jika tidak ada opsi yang dipilih
        $this->info("Pilih salah satu opsi: --all, --expired, --endpoint=*, --pattern=*, atau --maintenance");
    }
}
