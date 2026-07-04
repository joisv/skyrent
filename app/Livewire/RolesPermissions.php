<?php

namespace App\Livewire;

use App\Models\User;
use Livewire\Component;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Jantinnerezo\LivewireAlert\Facades\LivewireAlert;
use Illuminate\Validation\Rule;

class RolesPermissions extends Component
{
    public $search = '';
    public $sortField = 'created_at';
    public $sortDirection = 'desc';
    public $paginate = 10;

    public $mySelected = [];
    public $selectedAll = false;

    // 

    public $roleName;
    public $permissionName;

    public $roles;
    public $permissions;
    public $selectedRole = null;

    public $selectedPermissions = [];
    public $selectedRoles = [];
    public $selectedDirectPermissions = [];

    public $selectedRoleForUser = null;

    public User $selectedUser;

    public function render()
    {

        $query = $this->getData()->paginate($this->paginate);
        $this->roles = Role::all();
        $this->permissions = Permission::all();

        return view('livewire.roles-permissions', [
            'users' => $query,
        ]);
    }

    public function assignRoleForUser($userId)
    {
        $this->selectedUser = User::findOrFail($userId);
        $this->selectedRoleForUser = $this->selectedUser->roles->pluck('name')->first();
        $this->dispatch('open-modal', 'assign-role');
    }

    public function assignRole($role)
    {
        $this->selectedRole = $role;

        $this->selectedUser->syncRoles([$role]);
        
        $this->dispatch('close-modal');
        LivewireAlert::title('Role berhasil ditambahkan')
            ->position('top-end')
            ->text("Role {$role} berhasil ditambahkan")
            ->timer(4000)
            ->toast()
            ->success()
            ->show();

        $this->reset([
            'selectedRole',
            'selectedDirectPermissions',
            'selectedRoleForUser',
            'selectedUser',
        ]);
    }

    public function saveRole()
    {

        $this->validate([
            'roleName' => [
                'required',
                Rule::unique('roles', 'name'),
            ],
        ]);
        $role = Role::create([
            'name' => $this->roleName
        ]);

        $role->syncPermissions($this->selectedPermissions);

        $this->dispatch('close-modal');
        LivewireAlert::title('Role berhasil ditambahkan')
            ->position('top-end')
            ->text("Role {$this->roleName} berhasil ditambahkan")
            ->timer(4000)
            ->toast()
            ->success()
            ->show();

        $this->reset([
            'roleName',
            'selectedPermissions',
        ]);
    }

    public function savePermission()
    {
        $this->validate([
            'permissionName' => [
                'required',
                Rule::unique('permissions', 'name'),
            ],
        ]);

        Permission::create([
            'name' => $this->permissionName
        ]);

        $this->dispatch('close-modal');
        LivewireAlert::title('Permission berhasil ditambahkan')
            ->position('top-end')
            ->text("Permission {$this->permissionName} berhasil ditambahkan")
            ->timer(4000)
            ->toast()
            ->success()
            ->show();

        $this->reset([
            'permissionName',
        ]);
    }

    public function assignRolePermission()
    {
        $this->validate([
            'selectedRole' => [
                'required',
                Rule::exists('roles', 'name'),
            ],
            'selectedDirectPermissions' => [
                'required',
                'array',
                'min:1',
            ],
            'selectedDirectPermissions.*' => [
                Rule::exists('permissions', 'name'),
            ],
        ], [
            'selectedRole.required' => 'Silakan pilih role.',
            'selectedRole.exists' => 'Role tidak ditemukan.',

            'selectedDirectPermissions.required' => 'Pilih minimal satu permission.',
            'selectedDirectPermissions.array' => 'Format permission tidak valid.',
            'selectedDirectPermissions.min' => 'Pilih minimal satu permission.',
            'selectedDirectPermissions.*.exists' => 'Terdapat permission yang tidak valid.',
        ]);

        $role = Role::findByName($this->selectedRole);
        $role->syncPermissions($this->selectedDirectPermissions);
        $this->dispatch('close-modal');
        LivewireAlert::title('Assignment berhasil ditambahkan')
            ->position('top-end')
            ->text("Assignment berhasil ditambahkan")
            ->timer(4000)
            ->toast()
            ->success()
            ->show();

        $this->reset([
            'selectedDirectPermissions',
        ]);
    }

    public function getData()
    {
        $query = User::query()
            ->with(['roles', 'permissions']);

        if ($this->search) {
            $query->search([
                'name',
                'email',
                'updated_at',
            ], $this->search);
        }

        if (in_array($this->sortField, ['created_at', 'updated_at'])) {
            $query->orderBy($this->sortField, $this->sortDirection);
        }

        return $query;
    }
}
