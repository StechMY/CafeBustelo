<style>
    .team-modal {
        display: grid;
        gap: 1.25rem;
    }

    .team-modal__summary {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
    }

    .team-modal__title {
        margin: 0;
        font-size: 1rem;
        font-weight: 700;
        color: rgb(17 24 39);
    }

    .team-modal__desc {
        margin-top: 0.25rem;
        font-size: 0.875rem;
        color: rgb(107 114 128);
    }

    .team-modal__count {
        white-space: nowrap;
        border-radius: 999px;
        background: rgb(243 244 246);
        padding: 0.35rem 0.75rem;
        font-size: 0.75rem;
        font-weight: 700;
        color: rgb(55 65 81);
    }

    .team-modal__table-wrap {
        overflow-x: auto;
        border: 1px solid rgb(229 231 235);
        border-radius: 0.875rem;
        background: #ffffff;
    }

    .team-modal__table {
        width: 100%;
        min-width: 760px;
        border-collapse: collapse;
        font-size: 0.875rem;
    }

    .team-modal__table thead {
        background: rgb(249 250 251);
    }

    .team-modal__table th {
        padding: 0.75rem 1rem;
        text-align: left;
        font-weight: 700;
        color: rgb(55 65 81);
        border-bottom: 1px solid rgb(229 231 235);
    }

    .team-modal__table td {
        padding: 0.75rem 1rem;
        color: rgb(31 41 55);
        border-bottom: 1px solid rgb(243 244 246);
        vertical-align: middle;
    }

    .team-modal__table tbody tr:hover {
        background: rgb(249 250 251);
    }

    .team-modal__username {
        font-weight: 700;
        color: rgb(17 24 39);
    }

    .team-modal__badge {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: 0.25rem 0.625rem;
        font-size: 0.75rem;
        font-weight: 700;
        line-height: 1;
    }

    .team-modal__badge--level {
        background: rgb(239 246 255);
        color: rgb(29 78 216);
    }

    .team-modal__badge--active {
        background: rgb(220 252 231);
        color: rgb(21 128 61);
    }

    .team-modal__badge--inactive {
        background: rgb(243 244 246);
        color: rgb(75 85 99);
    }

    .team-modal__badge--suspended {
        background: rgb(254 226 226);
        color: rgb(185 28 28);
    }

    .team-modal__empty {
        border: 1px dashed rgb(209 213 219);
        border-radius: 0.875rem;
        padding: 1rem;
        font-size: 0.875rem;
        color: rgb(107 114 128);
        background: rgb(249 250 251);
    }

    .team-modal__muted {
        color: rgb(107 114 128);
    }

    @media (prefers-color-scheme: dark) {
        .team-modal__title {
            color: rgb(249 250 251);
        }

        .team-modal__desc {
            color: rgb(156 163 175);
        }

        .team-modal__count {
            background: rgb(31 41 55);
            color: rgb(229 231 235);
        }

        .team-modal__table-wrap {
            border-color: rgb(55 65 81);
            background: rgb(17 24 39);
        }

        .team-modal__table thead {
            background: rgb(31 41 55);
        }

        .team-modal__table th {
            color: rgb(229 231 235);
            border-bottom-color: rgb(55 65 81);
        }

        .team-modal__table td {
            color: rgb(209 213 219);
            border-bottom-color: rgb(31 41 55);
        }

        .team-modal__table tbody tr:hover {
            background: rgb(31 41 55);
        }

        .team-modal__username {
            color: rgb(249 250 251);
        }

        .team-modal__empty {
            border-color: rgb(75 85 99);
            background: rgb(31 41 55);
            color: rgb(156 163 175);
        }

        .team-modal__muted {
            color: rgb(156 163 175);
        }
    }
</style>

<div class="team-modal">
    <div class="team-modal__summary">
        <div>
            <h3 class="team-modal__title">
                All Downline
            </h3>

            <div class="team-modal__desc">
                Unlimited referral tree. Commission may still be limited by business rules.
            </div>
        </div>

        <span class="team-modal__count">
            {{ $downlineUsers->count() }} users
        </span>
    </div>

    @if ($downlineUsers->isNotEmpty())
        <div class="team-modal__table-wrap">
            <table class="team-modal__table">
                <thead>
                    <tr>
                        <th>Level</th>
                        <th>Username</th>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th>Referred By</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach ($downlineUsers as $user)
                        <tr>
                            <td>
                                <span class="team-modal__badge team-modal__badge--level">
                                    L{{ $user->team_depth }}
                                </span>
                            </td>

                            <td>
                                <span class="team-modal__username">
                                    {{ $user->username }}
                                </span>
                            </td>

                            <td>
                                {{ $user->name }}
                            </td>

                            <td>
                                {{ $user->phone ?: '-' }}
                            </td>

                            <td>
                                @php
                                    $statusClass = match ($user->status) {
                                        'active' => 'team-modal__badge--active',
                                        'suspended' => 'team-modal__badge--suspended',
                                        default => 'team-modal__badge--inactive',
                                    };
                                @endphp

                                <span class="team-modal__badge {{ $statusClass }}">
                                    {{ ucfirst($user->status) }}
                                </span>
                            </td>

                            <td>
                                <span class="team-modal__muted">
                                    {{ $user->referrer?->username ?: '-' }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="team-modal__empty">
            No downline users.
        </div>
    @endif
</div>