<?php

namespace App\Providers;

use App\Models\Booking;
use App\Observers\BookingObserver;
use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\View;
use App\Http\View\Composers\SeoComposer;

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
        Builder::macro('search', function ($fields, $string = null) {
            if ($string === null) {
                $string = $fields;
                $model = $this->getModel();
                if ($model instanceof \App\Models\Iphones) {
                    return $string ? $this->where(function ($query) use ($string) {
                        $query->where('name', 'like', '%' . $string . '%')
                            ->orWhere('asset_code', 'like', '%' . $string . '%')
                            ->orWhere('serial_number', 'like', '%' . $string . '%')
                            ->orWhere('storage', 'like', '%' . $string . '%')
                            ->orWhere('color', 'like', '%' . $string . '%');
                    }) : $this;
                }
                $fields = ['name'];
            }

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

        Booking::observe(BookingObserver::class);
        View::composer('*', SeoComposer::class);
    }
}
