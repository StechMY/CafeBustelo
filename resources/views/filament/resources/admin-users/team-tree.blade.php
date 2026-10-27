<div class="admin-team-tree">
    <style>
        .admin-team-tree { --team-border: #e5e7eb; --team-muted: #6b7280; --team-bg: #f9fafb; display: grid; gap: 1rem; }
        .dark .admin-team-tree { --team-border: #374151; --team-muted: #9ca3af; --team-bg: #1f2937; }
        .admin-team-tree__overview, .admin-team-tree__identity, .admin-team-tree__recharge { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem 1rem; }
        .admin-team-tree__help { color: var(--team-muted); font-size: .875rem; }
        .admin-team-tree__scroll { overflow: auto; max-height: 65vh; padding: .25rem; }
        .admin-team-tree__children { margin: .5rem 0 .5rem 1rem; padding-left: 1rem; border-left: 2px solid var(--team-border); }
        .admin-team-tree__node { margin-bottom: .5rem; min-width: 260px; }
        .admin-team-tree__node > summary { padding: .75rem; border: 1px solid var(--team-border); border-radius: .5rem; background: var(--team-bg); cursor: pointer; }
        .admin-team-tree__node > summary:focus-visible { outline: 2px solid #f59e0b; outline-offset: 2px; }
        .admin-team-tree__identity { display: inline-flex; }
        .admin-team-tree__recharge { margin-top: .5rem; font-size: .875rem; }
        .admin-team-tree__node .fi-badge { display: inline-flex; }
    </style>

    <div class="admin-team-tree__overview">
        <strong>{{ __('filament.managed_referral_user') }}: {{ $root->username }}</strong>
        <x-filament::badge color="gray">{{ __('filament.admin_team_members', ['count' => $memberCount]) }}</x-filament::badge>
        <x-filament::badge color="success">{{ __('filament.admin_team_recharged_count', ['count' => $rechargedCount]) }}</x-filament::badge>
    </div>
    <p class="admin-team-tree__help">{{ __('filament.admin_team_help') }}</p>
    <div class="admin-team-tree__scroll">
        @include('filament.resources.admin-users.team-node', ['member' => $root, 'depth' => 0])
    </div>
    @if ($memberCount === 0)
        <p class="admin-team-tree__help">{{ __('filament.admin_team_empty') }}</p>
    @endif
</div>
