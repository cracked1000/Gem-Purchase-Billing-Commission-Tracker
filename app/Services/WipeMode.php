<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Schema;

class WipeMode
{
    private ?bool $enabled = null;

    public function enabled(): bool
    {
        if ($this->enabled !== null) {
            return $this->enabled;
        }

        if (! Schema::hasTable('app_settings')) {
            return $this->enabled = false;
        }

        return $this->enabled = AppSetting::whereKey('wipe_mode')->value('value') === '1';
    }

    public function set(bool $enabled): void
    {
        AppSetting::updateOrCreate(
            ['key' => 'wipe_mode'],
            ['value' => $enabled ? '1' : '0'],
        );

        $this->enabled = $enabled;
    }
}
