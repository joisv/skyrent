<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Builder;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
         Builder::macro('search', function ($fields, $string) {
            if (!is_array($fields)) {
                $fields = [$fields];
            }

            return $string ? $this->where(function ($query) use ($fields, $string) {
                foreach ($fields as $field) {
                    // Jika nama kolom mengandung titik (.), itu menandakan relasi
                    if (strpos($field, '.') !== false) {
                        list($relation, $column) = explode('.', $field);
                        $query->orWhereHas($relation, function ($query) use ($column, $string) {
                            $query->where($column, 'like', '%' . $string . '%');
                        });
                    } else {
                        // Jika tidak ada titik (.), itu adalah kolom langsung pada tabel saat ini
                        $query->orWhere($field, 'like', '%' . $string . '%');
                    }
                }
            }) : $this;
        });
    }
}
