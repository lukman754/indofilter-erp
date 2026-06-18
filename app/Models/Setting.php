<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['company_id', 'key', 'value'];

    /**
     * Get a setting value by key, optionally scoped to a company.
     *
     * @param string $key
     * @param mixed $default
     * @param int|null $companyId
     * @return mixed
     */
    public static function get(string $key, $default = null, ?int $companyId = null)
    {
        $setting = self::where('key', $key)
            ->where('company_id', $companyId)
            ->first();
        return $setting ? $setting->value : $default;
    }

    /**
     * Set a setting value by key, optionally scoped to a company.
     *
     * @param string $key
     * @param mixed $value
     * @param int|null $companyId
     * @return Setting
     */
    public static function set(string $key, $value, ?int $companyId = null)
    {
        return self::updateOrCreate(
            ['company_id' => $companyId, 'key' => $key],
            ['value' => $value]
        );
    }
}
