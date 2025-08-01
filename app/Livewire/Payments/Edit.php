<?php

namespace App\Livewire\Payments;

use App\Models\Payment;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;
use Jantinnerezo\LivewireAlert\Facades\LivewireAlert;
use Illuminate\Support\Str;

class Edit extends Component
{
    use WithFileUploads;

    public $payment;
    public $name;
    public $slug;
    public $icon;
    public $description;
    public $is_active = true;

    public function update()
    {
        // dd($this->icon);
        $rules = [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ];

        if (is_object($this->icon)) {
            array_merge($rules, ['icon' => 'nullable|image|mimes:jpg,jpeg,png|max:2048']);
        }

        $this->validate($rules);

        $payment = $this->payment;

        // Hapus icon lama jika ada dan user upload icon baru
        if (is_object($this->icon)) {
            if ($payment->icon && Storage::disk('public')->exists($payment->icon)) {
                Storage::disk('public')->delete($payment->icon);
            }

            $iconPath = $this->icon->store('payments/icons', 'public');
            $payment->icon = $iconPath;
        }

        $payment->name = $this->name;
        $payment->description = $this->description;
        $payment->slug = $this->setSlugAttribute();
        $payment->save();

        $this->dispatch('close-modal');
        $this->reset(['name', 'icon', 'description', 'is_active']);

        LivewireAlert::title('Payment method berhasil diperbarui')
            ->text('Data payment telah diupdate')
            ->success()
            ->toast()
            ->position('top-end')
            ->show();
    }

    public function setSlugAttribute()
    {
        $slug = Str::slug($this->name);
        $originalSlug = $slug;
        $count = 2;

        while (Payment::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $count;
            $count++;
        }

        return $slug;
    }

    #[On('edit')]
    public function getPayment($payment)
    {
        $this->payment = Payment::find($payment);
        $this->name = $this->payment->name;
        $this->slug = $this->payment->slug;
        $this->icon = $this->payment->icon;
        $this->description = $this->payment->description;
        $this->is_active = $this->payment->is_active;
        // dd($this->icon);
    }

    public function render()
    {
        return view('livewire.payments.edit');
    }
}
