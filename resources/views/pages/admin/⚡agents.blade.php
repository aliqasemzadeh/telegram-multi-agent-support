<?php

use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Agents')] class extends Component {
    use WithPagination;

    public string $search = '';

    public ?int $editingId = null;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public bool $supportsPhone = true;

    public bool $supportsChat = true;

    public string $role = 'agent';

    public function mount(): void
    {
        abort_unless(Auth::user()?->can('agents.manage'), 403);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function agents()
    {
        return User::query()
            ->with('roles')
            ->when($this->search !== '', function ($query) {
                $query->where(function ($inner) {
                    $inner->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('email', 'like', '%'.$this->search.'%');
                });
            })
            ->orderBy('name')
            ->paginate(10);
    }

    public function create(): void
    {
        $this->resetForm();
        $this->modal('agent-form')->show();
    }

    public function edit(int $userId): void
    {
        $user = User::query()->with('roles')->findOrFail($userId);

        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->password = '';
        $this->supportsPhone = $user->supports_phone;
        $this->supportsChat = $user->supports_chat;
        $this->role = $user->roles->first()?->name ?? 'agent';

        $this->modal('agent-form')->show();
    }

    public function save(): void
    {
        abort_unless(Auth::user()?->can('agents.manage'), 403);

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->editingId),
            ],
            'supportsPhone' => ['boolean'],
            'supportsChat' => ['boolean'],
            'role' => ['required', Rule::in(['admin', 'agent'])],
            'password' => [$this->editingId ? 'nullable' : 'required', 'string', 'min:8'],
        ];

        $validated = $this->validate($rules);

        $user = $this->editingId
            ? User::query()->findOrFail($this->editingId)
            : new User;

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'supports_phone' => $validated['supportsPhone'],
            'supports_chat' => $validated['supportsChat'],
        ]);

        if (! empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        if (! $user->exists) {
            $user->email_verified_at = now();
        }

        $user->save();
        $user->syncRoles([$validated['role']]);

        $this->modal('agent-form')->close();
        $this->resetForm();
        unset($this->agents);

        Flux::toast(variant: 'success', text: __('app.agents.saved'));
    }

    public function delete(int $userId): void
    {
        abort_unless(Auth::user()?->can('agents.manage'), 403);

        if ($userId === Auth::id()) {
            return;
        }

        User::query()->whereKey($userId)->delete();
        unset($this->agents);

        Flux::toast(variant: 'success', text: __('app.agents.deleted'));
    }

    protected function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->email = '';
        $this->password = '';
        $this->supportsPhone = true;
        $this->supportsChat = true;
        $this->role = 'agent';
        $this->resetValidation();
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex flex-col gap-2">
            <flux:heading size="xl">{{ __('app.agents.title') }}</flux:heading>
            <flux:text>{{ __('app.agents.subtitle') }}</flux:text>
        </div>

        <flux:button variant="primary" color="teal" icon="user-plus" wire:click="create" class="w-full sm:w-auto">
            {{ __('app.agents.create') }}
        </flux:button>
    </div>

    <flux:card class="space-y-4">
        <flux:input wire:model.live.debounce.300ms="search" :placeholder="__('app.agents.search')" icon="magnifying-glass" />

        <flux:table :paginate="$this->agents">
            <flux:table.columns>
                <flux:table.column>{{ __('app.agents.name') }}</flux:table.column>
                <flux:table.column>{{ __('app.agents.email') }}</flux:table.column>
                <flux:table.column>{{ __('app.schedule.channel') }}</flux:table.column>
                <flux:table.column>{{ __('app.agents.role') }}</flux:table.column>
                <flux:table.column>{{ __('app.agents.actions') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($this->agents as $agent)
                    <flux:table.row wire:key="agent-{{ $agent->id }}">
                        <flux:table.cell>{{ $agent->name }}</flux:table.cell>
                        <flux:table.cell>{{ $agent->email }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="flex flex-wrap gap-1">
                                @if ($agent->supports_phone)
                                    <flux:badge color="sky" size="sm">{{ __('app.channel.phone') }}</flux:badge>
                                @endif
                                @if ($agent->supports_chat)
                                    <flux:badge color="emerald" size="sm">{{ __('app.channel.chat') }}</flux:badge>
                                @endif
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ __('app.roles.'.($agent->roles->first()?->name ?? 'agent')) }}
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex gap-1">
                                <flux:tooltip content="{{ __('app.common.edit') }}">
                                    <flux:button
                                        size="xs"
                                        variant="primary"
                                        color="cyan"
                                        icon="pencil-square"
                                        wire:click="edit({{ $agent->id }})"
                                    />
                                </flux:tooltip>
                                @if ($agent->id !== auth()->id())
                                    <flux:tooltip content="{{ __('app.common.delete') }}">
                                        <flux:button
                                            size="xs"
                                            variant="primary"
                                            color="red"
                                            icon="trash"
                                            wire:click="delete({{ $agent->id }})"
                                            wire:confirm="{{ __('app.common.are_you_sure') }}"
                                        />
                                    </flux:tooltip>
                                @endif
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal name="agent-form" flyout position="right" class="md:w-96">
        <div class="space-y-6">
            <flux:heading size="lg">
                {{ $editingId ? __('app.agents.edit') : __('app.agents.create') }}
            </flux:heading>

            <form wire:submit="save" class="space-y-4">
                <flux:input wire:model="name" :label="__('app.agents.name')" />
                <flux:error name="name" />

                <flux:input wire:model="email" type="email" :label="__('app.agents.email')" />
                <flux:error name="email" />

                <flux:input wire:model="password" type="password" :label="__('app.agents.password')" />
                <flux:error name="password" />

                <flux:select wire:model="role" :label="__('app.agents.role')">
                    <flux:select.option value="agent">{{ __('app.roles.agent') }}</flux:select.option>
                    <flux:select.option value="admin">{{ __('app.roles.admin') }}</flux:select.option>
                </flux:select>

                <flux:checkbox wire:model="supportsPhone" :label="__('app.agents.supports_phone')" />
                <flux:checkbox wire:model="supportsChat" :label="__('app.agents.supports_chat')" />

                <flux:button type="submit" variant="primary" color="orange" class="w-full">
                    {{ __('app.common.save') }}
                </flux:button>
            </form>
        </div>
    </flux:modal>
</section>
