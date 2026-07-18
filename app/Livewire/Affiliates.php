<?php

namespace App\Livewire;

use Illuminate\Support\Str;
use App\Models\Affiliate;
use App\Models\Booking;
use App\Models\Iphones;
use App\Models\Revenue;
use App\Models\User;
use Livewire\Attributes\On;
use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Jantinnerezo\LivewireAlert\Facades\LivewireAlert;
use Illuminate\Validation\Rule;

class Affiliates extends Component
{
    public $search = '';
    public $sortField = 'created_at';
    public $sortDirection = 'desc';
    public $paginate = 10;

    public $mySelected = [];
    public $selectedAll = false;

    public $users;

    public $selectedUser = null;
    public $selectedAffiliateId = null;

    public bool $showCreateDrawer = false;
    public string $code = '';
    public string $name = '';
    public string $slug = '';
    public ?string $email = null;
    public ?string $phone = null;
    public ?string $address = null;
    public ?string $city = null;
    public ?string $province = null;
    public ?string $postal_code = null;
    public ?string $latitude = null;
    public ?string $longitude = null;
    public ?string $description = null;
    public $logo;
    public $banner;

    public bool $is_active = true;

    public bool $is_edit = false;
    public $affiliate;
    public $detailAffiliate;
    public $selectedTab = 'users-tab';
    public $iphones;
    public $revenues;
    public $bookingsToday;
    public $allBookings;
    public $totalRevenue;

    protected function rules(): array
    {
        return [
            'code' => [
                'required',
                'max:10',
                Rule::unique('affiliates', 'code')
                    ->ignore($this->affiliate?->id),
            ],

            'name' => [
                'required',
                'max:255',
            ],

            'slug' => [
                'required',
                'max:255',
                Rule::unique('affiliates', 'slug')
                    ->ignore($this->affiliate?->id),
            ],

            'email' => 'nullable|email',
            'phone' => 'nullable|max:25',
            'address' => 'nullable',
            'city' => 'nullable',
            'province' => 'nullable',
            'postal_code' => 'nullable|max:10',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'description' => 'nullable',
            'logo' => 'nullable|image|max:2048',
            'banner' => 'nullable|image|max:4096',
            'is_active' => 'boolean',
        ];
    }

    public function assignUser($userId)
    {
        $this->selectedUser = User::findOrFail($userId);
        User::where('affiliate_id', $this->selectedAffiliateId)
            ->where('id', '!=', $this->selectedUser->id)
            ->update([
                'affiliate_id' => null,
            ]);

        $this->selectedUser->update([
            'affiliate_id' => $this->selectedAffiliateId,
        ]);
        $this->dispatch('close-modal-detail-affiliate');
        LivewireAlert::title('Berhasil menambahkan user ke affiliate')
            ->text('User berhasil ditambahkan.')
            ->success()
            ->toast()
            ->position('top-end')
            ->show();
    }

    public function listIphones($affiliateId)
    {
        $this->dispatch('open-modal', 'list-iphones');
    }

    public function listBookings($affiliateId)
    {
        $this->dispatch('open-modal', 'list-bookings');
    }

    public function DetailAffiliateuser($affiliateId)
    {
        $this->dispatch('open-modal', 'detail-affiliate');

        $this->detailAffiliate = Affiliate::with([
            'users.roles',
            'iphones',
            'bookings' => function ($query) use ($affiliateId) {
                $query->where(function ($q) use ($affiliateId) {
                    $q->where('affiliate_id', $affiliateId)
                        ->orWhere(function ($sub) use ($affiliateId) {
                            $affiliate = Affiliate::with('users')->find($affiliateId);

                            if ($user = $affiliate?->users->first()) {
                                $sub->whereNull('affiliate_id')
                                    ->where('user_id', $user->id);
                            }
                        });
                });
            },
            'bookings.revenue',
        ])->findOrFail($affiliateId);

        $user = $this->detailAffiliate->users->first();

        if (!empty($user)) {
            # code...
            $this->revenues = Revenue::whereDate('created_at', today())
                ->whereHas('booking', function ($query) use ($user) {
                    $query->where(function ($q) use ($user) {
                        $q->where('affiliate_id', $user->affiliate_id)
                            ->orWhere(function ($sub) use ($user) {
                                $sub->whereNull('affiliate_id')
                                    ->where('user_id', $user->id);
                            });
                    });
                })
                ->with(['booking', 'booking.iphone'])
                ->get();
            $this->totalRevenue = Revenue::whereHas('booking', function ($query) use ($user) {
                $query->where(function ($q) use ($user) {
                    $q->where('affiliate_id', $user->affiliate_id)
                        ->orWhere(function ($sub) use ($user) {
                            $sub->whereNull('affiliate_id')
                                ->where('user_id', $user->id);
                        });
                });
            })
                ->with([
                    'booking',
                    'booking.iphone',
                ])
                ->get();
            $this->allBookings = Booking::where(function ($query) use ($user) {
                $query->where('affiliate_id', $this->detailAffiliate->id)
                    ->orWhere(function ($q) use ($user) {
                        $q->whereNull('affiliate_id')
                            ->where('user_id', $user->id);
                    });
            })->with(['iphone', 'revenue'])->get();
    
            $this->bookingsToday = Booking::whereDate('created_at', today())
                ->where(function ($query) use ($user) {
                    $query->where('affiliate_id', $user->affiliate_id)
                        ->orWhere(function ($sub) use ($user) {
                            $sub->whereNull('affiliate_id')
                                ->where('user_id', $user->id);
                        });
                })
                ->with(['revenue', 'iphone'])
                ->get();
    
            $query = Iphones::query();
    
            if ($user?->hasRole('affiliate-admin')) {
                $query->where('affiliate_id', $user->affiliate_id);
            } elseif ($user?->hasRole('super-admin')) {
                $query->whereNull('affiliate_id');
            }
    
            $this->iphones = $query->get();
        }

        $this->selectedAffiliateId = $affiliateId;
    }

    public function assignAffiliateToUser($affiliateId)
    {
        $this->dispatch('open-modal', 'assign-user-to-affiliate');
        $this->selectedUser = Affiliate::where('id', $affiliateId)->firstOrFail()->users()->first();
        $this->selectedAffiliateId = $affiliateId;
    }

    public function edit($affiliateId)
    {
        $this->affiliate = Affiliate::findOrFail($affiliateId);
        $this->is_edit = true;

        $this->code = $this->affiliate->code;
        $this->name = $this->affiliate->name;
        $this->slug = $this->affiliate->slug;
        $this->email = $this->affiliate->email;
        $this->phone = $this->affiliate->phone;
        $this->address = $this->affiliate->address;
        $this->city = $this->affiliate->city;
        $this->province = $this->affiliate->province;
        $this->postal_code = $this->affiliate->postal_code;
        $this->latitude = $this->affiliate->latitude;
        $this->longitude = $this->affiliate->longitude;
        $this->description = $this->affiliate->description;
        $this->is_active = $this->affiliate->is_active;

        $this->showCreateDrawer = true;
    }

    public function store(): void
    {
        $validated = $this->validate();

        DB::transaction(function () use ($validated) {

            if ($this->is_edit === false) {
                Affiliate::create([
                    'code'        => $validated['code'],
                    'name'        => $validated['name'],
                    'slug'        => $validated['slug'],
                    'email'       => $validated['email'],
                    'phone'       => $validated['phone'],
                    'address'     => $validated['address'],
                    'city'        => $validated['city'],
                    'province'    => $validated['province'],
                    'postal_code' => $validated['postal_code'],
                    'latitude'    => $validated['latitude'],
                    'longitude'   => $validated['longitude'],
                    'description' => $validated['description'],
                    'logo'        => $this->logo
                        ? $this->logo->store('affiliates/logo', 'public')
                        : null,
                    'banner'      => $this->banner
                        ? $this->banner->store('affiliates/banner', 'public')
                        : null,
                    'is_active'   => $this->is_active,
                ]);
            } else {
                $this->affiliate->update([
                    'code'        => $validated['code'],
                    'name'        => $validated['name'],
                    'slug'        => $validated['slug'],
                    'email'       => $validated['email'],
                    'phone'       => $validated['phone'],
                    'address'     => $validated['address'],
                    'city'        => $validated['city'],
                    'province'    => $validated['province'],
                    'postal_code' => $validated['postal_code'],
                    'latitude'    => $validated['latitude'],
                    'longitude'   => $validated['longitude'],
                    'description' => $validated['description'],
                    'logo'        => $this->logo
                        ? $this->logo->store('affiliates/logo', 'public')
                        : $this->affiliate->logo,
                    'banner'      => $this->banner
                        ? $this->banner->store('affiliates/banner', 'public')
                        : $this->affiliate->banner,
                    'is_active'   => $this->is_active,
                ]);
            }
        });

        $this->reset();

        $this->is_active = true;
        $this->showCreateDrawer = false;
        $this->reset([
            'code',
            'name',
            'slug',
            'email',
            'phone',
            'address',
            'city',
            'province',
            'postal_code',
            'latitude',
            'longitude',
            'description',
            'logo',
            'banner',
            'is_active',
            'is_edit',
            'affiliate',
        ]);
        LivewireAlert::title('Berhasil menambahkan Affiliate')
            ->text('Affiliate berhasil ditambahkan.')
            ->success()
            ->toast()
            ->position('top-end')
            ->show();
    }

    public function render()
    {
        // $this->users = User::whereHas('roles', function ($query) {
        //     $query->where('name', 'affiliate-admin');
        // })->get();

        $this->users =  User::all();


        return view('livewire.affiliates', [
            'affiliates' => $this->getData()->paginate(10)
        ]);
    }

    #[On('setslug')]
    public function setSlugAttribute()
    {
        $slug = Str::slug($this->name);
        $originalSlug = $slug;
        $count = 2;

        while (Affiliate::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $count;
            $count++;
        }

        $this->slug = $slug;
    }

    public function getData()
    {
        $query = Affiliate::query();

        if ($this->search) {
            $query->search(['name', 'description', 'updated_at'], $this->search);
        }

        if (in_array($this->sortField, ['created_at', 'updated_at'])) {

            $query->orderBy($this->sortField, $this->sortDirection);
        }

        // Jangan panggil get() di sini, biarkan query builder tetap sebagai objek query
        return $query;
    }

    public function destroyAlert($value = '', $onConfirm = 'destroy')
    {

        LivewireAlert::title('Delete this affiliates ?')
            ->warning()
            ->toast()
            ->position('top-end')
            ->withConfirmButton('Delete')
            ->confirmButtonColor('green')
            ->cancelButtonColor('red')
            ->withCancelButton('Cancel')
            ->onConfirm($onConfirm, ['data' => $value])
            ->show();
    }

    public function delete($data)
    {
        if (auth()->user()->can('delete')) {
            $this->mySelected[] = $data['data'];
            $this->destroy('deleted successfully');
        } else {
            LivewireAlert::title('kamu tidak memiliki izin')
                ->position('top-end')
                ->toast()
                ->text('tidak dapat menghapus data')
                ->timer(5000)
                ->error()
                ->show();
            $this->mySelected = [];
            $this->selectedAll = false;
        }
    }

    public function destroy()
    {
        if (auth()->user()->can('delete')) {
            if ($this->mySelected) {
                try {
                    //code...
                    // dd('masuk ke try');
                    Affiliate::whereIn('id', $this->mySelected)->delete();
                    $this->mySelected = [];
                    $this->selectedAll = false;
                    LivewireAlert::title('Data berhasil dihapus')
                        ->position('top-end')
                        ->toast()
                        ->text('bulk delete data berhasil')
                        ->timer(5000)
                        ->success()
                        ->show();
                } catch (\Throwable $th) {
                    LivewireAlert::title('Affiliate tidak ditemukan')
                        ->position('top-end')
                        ->toast()
                        ->text('tidak dapat menghapus data')
                        ->timer(5000)
                        ->error()
                        ->show();
                }
            } else {
                LivewireAlert::title('Affiliate tidak ditemukan 2')
                    ->position('top-end')
                    ->toast()
                    ->text('tidak dapat menghapus data')
                    ->timer(5000)
                    ->error()
                    ->show();
            }
        } else {
            LivewireAlert::title('kamu tidak memiliki izin')
                ->position('top-end')
                ->toast()
                ->text('tidak dapat menghapus data')
                ->timer(5000)
                ->error()
                ->show();
            $this->mySelected = [];
            $this->selectedAll = false;
        }
    }
}
