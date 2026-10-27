@php($children = $childrenByParent->get($member->id, collect()))
<details class="admin-team-tree__node" data-team-user="{{ $member->id }}" @if ($depth < 2) open @endif>
    <summary>
        <span class="admin-team-tree__identity">
            <strong>{{ $member->username }}</strong>
            @if ($member->name && $member->name !== $member->username)
                <span>{{ $member->name }}</span>
            @endif
            <span class="admin-team-tree__help">{{ $depth === 0 ? __('filament.admin_team_root') : __('filament.admin_team_level', ['level' => $depth]) }}</span>
            <span class="admin-team-tree__help">{{ __('filament.admin_team_direct', ['count' => $children->count()]) }}</span>
            @if ($member->is_virtual)
                <x-filament::badge color="gray">{{ __('filament.virtual') }}</x-filament::badge>
            @endif
        </span>
        <span class="admin-team-tree__recharge">
            <x-filament::badge :color="$member->first_recharge_amount !== null ? 'success' : 'gray'">
                {{ $member->first_recharge_amount !== null ? __('filament.admin_team_recharged') : __('filament.admin_team_not_recharged') }}
            </x-filament::badge>
            @if ($member->first_recharge_amount !== null)
                <span>{{ __('filament.admin_team_first_amount') }}: {{ config('app.currency') }} {{ number_format((float) $member->first_recharge_amount, 2) }}</span>
                <span>{{ __('filament.admin_team_first_at') }}: {{ $member->first_recharge_at ?? '—' }}</span>
            @endif
        </span>
    </summary>
    @if ($children->isNotEmpty())
        <div class="admin-team-tree__children">
            @foreach ($children as $child)
                @include('filament.resources.admin-users.team-node', ['member' => $child, 'depth' => $depth + 1])
            @endforeach
        </div>
    @endif
</details>
