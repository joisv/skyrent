<?php

namespace App\Livewire\Settings;

use App\Models\AppRelease;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class AppUpdates extends Component
{
    use WithFileUploads, WithPagination;

    public $version_name = '';
    public $build_number = '';
    public $min_supported_build = '';
    public $release_notes = '';
    public $is_active = true;
    public $apk_file;

    public $showCreateModal = false;
    public $search = '';

    protected function rules(): array
    {
        return [
            'version_name' => ['required', 'string', 'max:50'],
            'build_number' => ['required', 'integer', 'min:1', 'unique:app_releases,build_number'],
            'min_supported_build' => ['nullable', 'integer', 'min:1', 'lte:build_number'],
            'release_notes' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['boolean'],
            'apk_file' => ['required', 'file', 'max:256000'], // max 250MB
        ];
    }

    protected function messages(): array
    {
        return [
            'apk_file.max' => 'Ukuran berkas APK tidak boleh melebihi 250 MB.',
            'apk_file.required' => 'Berkas APK wajib diunggah.',
            'build_number.unique' => 'Nomor build ini sudah terdaftar pada rilis lain.',
            'min_supported_build.lte' => 'Minimum build didukung tidak boleh lebih tinggi dari nomor build rilis.',
        ];
    }

    public function mount(): void
    {
        // Suggest next build number based on highest existing build
        $highestBuild = AppRelease::max('build_number') ?? 1;
        $this->build_number = (int) $highestBuild + 1;
    }

    public function openCreateModal(): void
    {
        $this->resetErrorBag();
        $highestBuild = AppRelease::max('build_number') ?? 1;
        $this->build_number = (int) $highestBuild + 1;
        $this->showCreateModal = true;
    }

    public function closeCreateModal(): void
    {
        $this->showCreateModal = false;
        $this->reset(['version_name', 'min_supported_build', 'release_notes', 'apk_file']);
        $this->is_active = true;
    }

    public function save(): void
    {
        $this->validate();

        // Ensure file has .apk extension
        $clientExt = strtolower($this->apk_file->getClientOriginalExtension());
        if ($clientExt !== 'apk') {
            $this->addError('apk_file', 'Berkas harus berupa file biner berekstensi .apk');
            return;
        }

        $realPath = $this->apk_file->getRealPath();
        $checksum = hash_file('sha256', $realPath);
        $size = $this->apk_file->getSize();

        // Store APK in private local storage (storage/app/private/apks/)
        $filename = 'skymobile_' . Str::uuid() . '.apk';
        $storedPath = $this->apk_file->storeAs('apks', $filename, 'local');

        AppRelease::create([
            'version_name' => trim($this->version_name),
            'build_number' => (int) $this->build_number,
            'min_supported_build' => !empty($this->min_supported_build) ? (int) $this->min_supported_build : null,
            'release_notes' => trim($this->release_notes) ?: null,
            'apk_path' => $storedPath,
            'apk_size' => $size,
            'apk_checksum_sha256' => $checksum,
            'is_active' => (bool) $this->is_active,
            'created_by' => Auth::id(),
        ]);

        $this->closeCreateModal();
        session()->flash('message', "Rilis SkyMobile v{$this->version_name} (Build {$this->build_number}) berhasil diunggah.");
    }

    public function toggleActive(int $id): void
    {
        $release = AppRelease::findOrFail($id);
        $release->is_active = !$release->is_active;
        $release->save();

        session()->flash('message', "Status rilis v{$release->version_name} diperbarui.");
    }

    public function deleteRelease(int $id): void
    {
        $release = AppRelease::findOrFail($id);
        $v = $release->version_name;
        $release->delete(); // deletion hook automatically cleans storage/app/private/apks/ file

        session()->flash('message', "Rilis v{$v} berhasil dihapus.");
    }

    public function render()
    {
        $query = AppRelease::query()->with('creator')->orderBy('build_number', 'desc');

        if (!empty($this->search)) {
            $s = trim($this->search);
            $query->where(function ($q) use ($s) {
                $q->where('version_name', 'like', "%{$s}%")
                    ->orWhere('build_number', 'like', "%{$s}%")
                    ->orWhere('release_notes', 'like', "%{$s}%");
            });
        }

        $releases = $query->paginate(10);
        $latestActive = AppRelease::latestActive();

        return view('livewire.settings.app-updates', [
            'releases' => $releases,
            'latestActive' => $latestActive,
        ]);
    }
}
