<div x-data="{
    createUser() {
            $dispatch('create-chapter')
            $dispatch('open-modal', 'user-create')
        },
        createRolePermission() {
            $dispatch('open-modal', 'role-permission-create')
        },
        permissionCreate() {
            $dispatch('open-modal', 'permission-create')
        },
        editUser(id) {

            $dispatch('open-modal', 'chapter-edit');
            $dispatch('edit', { value: id });

        }
}">
    <x-tables.table name="Users" count="{{ $users->count() }} Users">
        <x-slot name="secondBtn">
            <button
                class="flex items-center justify-center w-1/2 px-5 py-2 text-sm disabled:text-gray-700 transition-colors duration-200 disabled:bg-white border rounded-lg gap-x-2 sm:w-auto dark:hover:bg-gray-800 dark:bg-gray-900 hover:bg-gray-100 dark:text-gray-200 dark:border-gray-700 bg-red-500 text-white"
                wire:click="destroyAlert" @if (!$mySelected) disabled @endif>
                <span>Bulk delete</span>
            </button>
        </x-slot>
        <x-slot name="addBtn">
            <x-tables.addbtn type="button" x-data="" @click="createUser">
                Add User
            </x-tables.addbtn>
            <x-tables.addbtn type="button" x-data="" @click="createRolePermission">
                Roles
            </x-tables.addbtn>
            <x-tables.addbtn type="button" x-data="" @click="permissionCreate">
                Permissions
            </x-tables.addbtn>
            <x-tables.addbtn type="button" x-data=""
                @click="$dispatch('open-modal', 'assign-role-permission')">
                Assign Role & Permission
            </x-tables.addbtn>
        </x-slot>
        <x-slot name="sort">
            <div class="flex items-center space-x-2 w-1/2 ">
                <div class="w-fit">
                    <select id="sort_series"
                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 px-5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
                        wire:model.live="paginate">
                        <option value="10">10</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                        <option value="150">150</option>
                    </select>
                </div>
                <div class="w-fit">
                    <select id="sort"
                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 px-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
                        wire:model.live="sortField">
                        <option selected>Filter by</option>
                        <option value="created_at">All</option>
                        <option value="updated_at">Updated</option>
                    </select>
                </div>
            </div>
        </x-slot>
        <x-slot name="search">
            <x-search wire:model.live.debounce.500ms="search" />
        </x-slot>
        <x-slot name="thead">
            <x-tables.th>
                <input id="selectedAll" type="checkbox"
                    class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600"
                    wire:model.live="selectedAll">
            </x-tables.th>
            <x-tables.th>Name</x-tables.th>
            <x-tables.th>Email</x-tables.th>
            <x-tables.th>Role</x-tables.th>
            <x-tables.th>Created</x-tables.th>
            <x-tables.th>Updated</x-tables.th>
            <x-tables.th>Action</x-tables.th>
        </x-slot>

        <x-slot name="tbody">
            @foreach ($users as $index => $user)
                <tr>
                    <x-tables.td>
                        <input id="default-{{ $index }}" type="checkbox"
                            class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600"
                            wire:model.live="mySelected" value="{{ $user->id }}">
                    </x-tables.td>
                    <x-tables.td>{{ $user->name }}</x-tables.td>
                    <x-tables.td>{{ $user->email }}</x-tables.td>
                    <x-tables.td>
                        {{ $user->getRoleNames()->first() ?? '-' }}
                    </x-tables.td>
                    <x-tables.td>{{ $user->created_at->format('F j, Y') }}</x-tables.td>
                    <x-tables.td>{{ $user->updated_at->format('d M Y') }}</x-tables.td>
                    <x-tables.td>
                        <x-primary-button type="button" wire:click="assignRoleForUser('{{ $user->id }}')">Assign
                            Role</x-primary-button>
                        <x-danger-button type="button"
                            wire:click="destroyAlert('{{ $user->id }}', 'delete')">delete</x-danger-button>
                    </x-tables.td>
                </tr>
            @endforeach
        </x-slot>

    </x-tables.table>
    <div class="w-full mt-5">
        {{ $users->links() }}
    </div>
    <x-modal name="assign-role" :show="$errors->isNotEmpty()" maxWidth="lg">
        <div class="max-w-3xl p-4" @close-modal.window="show = false">

            <div class="mb-5">
                <h2 class="text-lg font-semibold text-gray-800">
                    Assign Role
                </h2>

                <p class="text-sm text-gray-500">
                    Pilih salah satu role untuk user.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                @foreach ($roles as $role)
                    <button wire:click="assignRole('{{ $role->name }}')"
                        class="group w-full text-left rounded-xl border transition duration-200 p-5

                {{ $selectedRoleForUser == $role->name
                    ? 'border-orange-600 bg-orange-50'
                    : 'border-gray-200 hover:border-orange-300 hover:bg-gray-50' }}">

                        <div class="flex justify-between items-center">

                            <div>

                                <h3 class="font-semibold text-gray-800">
                                    {{ ucfirst($role->name) }}
                                </h3>

                                <p class="text-sm text-gray-500 mt-1">
                                    {{ $role->permissions->count() }} Permissions
                                </p>

                            </div>

                            @if ($selectedRole == $role->name)
                                <div
                                    class="h-8 w-8 rounded-full bg-orange-600 text-white flex items-center justify-center">

                                    ✓

                                </div>
                            @endif

                        </div>

                    </button>
                @endforeach

            </div>

        </div>
    </x-modal>
    {{-- <x-modal name="user-create" :show="$errors->isNotEmpty()" maxWidth="sm" focusable>
        <livewire:settings.user-create />
    </x-modal> --}}
    <x-modal name="role-permission-create" :show="$errors->isNotEmpty()" maxWidth="lg">
        <div class="p-6" @close-modal.window="show = false">
            <h2 class="text-xl font-semibold mb-4">
                Tambah Role
            </h2>

            <div class="space-y-4">

                <div>
                    <label class="block mb-1">Nama Role</label>

                    <input type="text" class="w-full border rounded-lg" wire:model.defer="roleName">
                    @error('roleName')
                        <span class="error">{{ $message }}</span>
                    @enderror
                </div>

                <div>

                    <label class="block mb-2">
                        Permissions
                    </label>

                    <div class="grid grid-cols-2 gap-2 max-h-64 overflow-y-auto p-2">

                        @foreach ($permissions as $permission)
                            <label>
                                <input type="checkbox" value="{{ $permission->name }}"
                                    wire:model="selectedPermissions">

                                {{ $permission->name }}
                            </label>
                        @endforeach
                    </div>

                </div>

            </div>

            <div class="flex justify-end mt-6 gap-2">

                <button wire:click="$set('showRoleModal',false)" class="px-4 py-2 border rounded">

                    Batal

                </button>

                <button wire:click="saveRole" class="px-4 py-2 bg-blue-600 text-white rounded">

                    Simpan

                </button>

            </div>

        </div>
    </x-modal>
    <x-modal name="permission-create" :show="$errors->isNotEmpty()" maxWidth="lg">
        <div class="p-6" @close-modal.window="show = false">

            <h2 class="text-xl font-semibold mb-4">

                Tambah Permission

            </h2>

            <input type="text" wire:model.defer="permissionName" class="w-full border rounded-lg">
            @error('permissionName')
                <span class="error">{{ $message }}</span>
            @enderror

            <div class="flex justify-end mt-6">

                <button wire:click="savePermission" class="bg-blue-600 text-white px-4 py-2 rounded">

                    Simpan

                </button>

            </div>

        </div>
    </x-modal>
    <x-modal name="assign-role-permission" :show="$errors->isNotEmpty()" maxWidth="2xl">
        <div class="p-6" @close-modal.window="show = false">

            <h2 class="text-xl font-semibold mb-6">

                Assign Role & Permission

            </h2>

            <div class="mb-5">

                <h3 class="font-semibold">
                    Roles
                </h3>

                <div class="grid grid-cols-2 gap-2 p-3">

                    @foreach ($roles as $role)
                        <label class="flex items-center gap-2">
                            <input type="radio" value="{{ $role->name }}" wire:model="selectedRole">

                            {{ $role->name }}
                        </label>
                    @endforeach
                </div>
                @error('selectedRole')
                    <span class="text-red-500 text-sm">{{ $message }}</span>
                @enderror

            </div>

            <div>

                <h3 class="font-semibold mb-2">
                    Direct Permission
                </h3>

                <div class="grid grid-cols-2 gap-2 max-h-64 overflow-y-auto p-3">

                    @foreach ($permissions as $permission)
                        <label class="flex items-center gap-2">

                            <input type="checkbox" value="{{ $permission->name }}"
                                wire:model="selectedDirectPermissions">

                            {{ $permission->name }}

                        </label>
                    @endforeach
                </div>
                @error('selectedDirectPermissions')
                    <span class="text-red-500 text-sm">{{ $message }}</span>
                @enderror

            </div>

            <div class="flex justify-end mt-6">

                <button wire:click="assignRolePermission" class="bg-blue-600 text-white px-5 py-2 rounded">

                    Simpan

                </button>

            </div>

        </div>
    </x-modal>
</div>
