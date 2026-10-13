@php
    $user = auth()->user();
    if (!$user) return;

    $currentRoute = request()->route()?->getName() ?? '';
    $currentUrl = request()->url();

    $isCurrent = function ($routeName, $pathSegment = '') use ($currentRoute, $currentUrl) {
        if ($routeName && $currentRoute === $routeName) {
            return true;
        }
        if ($routeName && fnmatch($routeName . '*', $currentRoute)) {
            return true;
        }
        if ($pathSegment && str_contains($currentUrl, '/admin/' . $pathSegment)) {
            return true;
        }
        return false;
    };

    $isDashboard = ($currentRoute === 'filament.admin.pages.dashboard' || $currentUrl === url('/admin') || $currentUrl === url('/admin/'));

    // Cache pending approvals/actions for role badge for 10 seconds to speed up page loads
    $pendingData = cache()->remember("mobile_badge_counts_user_{$user->id}", 10, function () use ($user) {
        $pCount = 0;
        $pFinaBbm = 0;
        $pFinaNonBbm = 0;
        try {
            if ($user->isAsm()) {
                $empId = $user->getEffectiveEmployeeId();
                $pCount = \App\Models\Claim::where('approval_status', 'DIAJUKAN')
                    ->where('user_id', '!=', $user->id)
                    ->where(function ($q) use ($user, $empId) {
                        if ($empId) {
                            $q->whereHas('employee', fn ($eq) => $eq->where('supervisor_id', $empId));
                        }
                        $regions = $user->getRegionList();
                        if (!empty($regions)) {
                            $q->orWhereHas('employee', fn ($eq) => $eq->whereIn('region', $regions));
                        }
                    })->count();
            } elseif ($user->isRgm()) {
                $empId = $user->getEffectiveEmployeeId();
                $pCount = \App\Models\Claim::where(function ($q) {
                        $q->where('approval_status', 'ACC_ASM')
                          ->orWhere(function ($sub) {
                              $sub->where('approval_status', 'DIAJUKAN')
                                  ->whereHas('employee.role', fn ($rq) => $rq->where('code', 'ASM'));
                          });
                    })
                    ->where('user_id', '!=', $user->id)
                    ->where(function ($q) use ($user, $empId) {
                        if ($empId) {
                            $q->whereHas('employee', fn ($eq) => $eq->where('supervisor_id', $empId))
                              ->orWhereHas('employee.supervisor', fn ($sq) => $sq->where('supervisor_id', $empId));
                        }
                        $regions = $user->getRegionList();
                        if (!empty($regions)) {
                            $q->orWhereHas('employee', fn ($eq) => $eq->whereIn('region', $regions));
                        }
                    })->count();
            } elseif ($user->isJejen()) {
                $pCount = \App\Models\Claim::pendingJejenApproval($user)->count();
            } elseif ($user->isYoga()) {
                $pCount = \App\Models\Claim::pendingYogaApproval()->count();
            } elseif ($user->isNurbaiti()) {
                $pCount = \App\Models\Claim::pendingNurbaitiApproval()->count();
            } elseif ($user->isThia()) {
                $pCount = \App\Models\Claim::pendingThiaApproval()->count();
            } elseif ($user->isAdmin() && !$user->isSuperAdmin()) {
                $pCount = \App\Models\Claim::pendingAdminVerification($user)->count();
            } elseif ($user->isFinance()) {
                $pFinaBbm = \App\Models\Claim::pendingFinanceBbmDisbursement()->count();
                $pFinaNonBbm = \App\Models\Claim::pendingFinanceNonBbmDisbursement()->count();
                $pCount = $pFinaBbm + $pFinaNonBbm;
            }
        } catch (\Throwable $e) {}

        return [
            'pendingCount' => $pCount,
            'pendingFinanceBbm' => $pFinaBbm,
            'pendingFinanceNonBbm' => $pFinaNonBbm,
        ];
    });

    $pendingCount = $pendingData['pendingCount'];
    $pendingFinanceBbm = $pendingData['pendingFinanceBbm'];
    $pendingFinanceNonBbm = $pendingData['pendingFinanceNonBbm'];
@endphp

<nav class="mobile-bottom-nav" aria-label="Navigasi Mobile"
     x-data="{
         currentPath: window.location.pathname,
         isModalOpen: false,
         checkModal() {
             const visibleModal = Array.from(document.querySelectorAll('.fi-modal.fi-modal-open, .fi-modal-open .fi-modal-window')).some(el => {
                 return el && el.offsetParent !== null && window.getComputedStyle(el).display !== 'none' && window.getComputedStyle(el).visibility !== 'hidden';
             });
             this.isModalOpen = Boolean(
                 visibleModal ||
                 document.body.classList.contains('fi-modal-open')
             );
         },
         init() {
             this.checkModal();
             const obs = new MutationObserver(() => this.checkModal());
             obs.observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['class'] });
             window.addEventListener('open-modal', () => setTimeout(() => this.checkModal(), 50));
             window.addEventListener('close-modal', () => setTimeout(() => this.checkModal(), 50));
         }
     }"
     x-show="!isModalOpen"
     x-cloak
     x-on:livewire:navigated.window="currentPath = window.location.pathname">
    <div class="grid items-center justify-around h-16 max-w-lg mx-auto px-1" style="grid-template-columns: repeat({{ $user->isExecutiveApprover() ? 3 : ($user->isSales() ? 4 : 5) }}, minmax(0, 1fr));">

        {{-- 1. BERANDA (Semua Role) --}}
        <a href="{{ route('filament.admin.pages.dashboard') }}"
           class="mobile-bottom-nav-item" :class="{ 'is-active': currentPath === '/admin' || currentPath === '/admin/' }">
            <svg class="w-6 h-6 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
            </svg>
            <span class="mobile-bottom-nav-label">Beranda</span>
        </a>

        @if($user->isFinance())
            {{-- ========================================================
               ROLE FINANCE
               ======================================================== --}}
            {{-- Tab 2: Cairkan BBM --}}
            <a href="{{ route('filament.admin.resources.finance-bbm-claims.index') }}"
               class="mobile-bottom-nav-item" :class="{ 'is-active': currentPath.includes('/admin/finance-bbm-claims') }">
                <svg class="w-6 h-6 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" />
                </svg>
                @if($pendingFinanceBbm > 0)
                    <span class="mobile-nav-badge">{{ $pendingFinanceBbm > 99 ? '99+' : $pendingFinanceBbm }}</span>
                @endif
                <span class="mobile-bottom-nav-label">Cairkan BBM</span>
            </a>

            {{-- Tab 3: Cairkan Non-BBM --}}
            <a href="{{ route('filament.admin.resources.finance-non-bbm-claims.index') }}"
               class="mobile-bottom-nav-item" :class="{ 'is-active': currentPath.includes('/admin/finance-non-bbm-claims') }">
                <svg class="w-6 h-6 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6H2.25m0 0v8.25m0 0a60.208 60.208 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V6.75A.75.75 0 0 0 18.75 6H18a.75.75 0 0 1-.75-.75V4.5m0 0a60.082 60.082 0 0 0-13.5 0" />
                </svg>
                @if($pendingFinanceNonBbm > 0)
                    <span class="mobile-nav-badge">{{ $pendingFinanceNonBbm > 99 ? '99+' : $pendingFinanceNonBbm }}</span>
                @endif
                <span class="mobile-bottom-nav-label">Non-BBM</span>
            </a>

            {{-- Tab 4: Semua Klaim --}}
            <a href="{{ route('filament.admin.resources.claims.index') }}"
               class="mobile-bottom-nav-item" :class="{ 'is-active': currentPath.includes('/admin/claims') }">
                <svg class="w-6 h-6 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" />
                </svg>
                <span class="mobile-bottom-nav-label">Semua Klaim</span>
            </a>

        @elseif($user->isAdmin() || $user->isSuperAdmin())
            {{-- ========================================================
               ROLE ADMIN & SUPERADMIN
               ======================================================== --}}
            {{-- Tab 2: Verifikasi Admin --}}
            <a href="{{ route('filament.admin.resources.admin-claim-approvals.index') }}"
               class="mobile-bottom-nav-item" :class="{ 'is-active': currentPath.includes('/admin/admin-claim-approvals') }">
                <svg class="w-6 h-6 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 0 1-1.043 3.296 3.745 3.745 0 0 1-3.296 1.043A3.745 3.745 0 0 1 12 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 0 1-3.296-1.043 3.745 3.745 0 0 1-1.043-3.296A3.745 3.745 0 0 1 3 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 0 1 1.043-3.296 3.746 3.746 0 0 1 3.296-1.043A3.746 3.746 0 0 1 12 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 0 1 3.296 1.043 3.746 3.746 0 0 1 1.043 3.296A3.745 3.745 0 0 1 21 12Z" />
                </svg>
                @if($pendingCount > 0)
                    <span class="mobile-nav-badge">{{ $pendingCount > 99 ? '99+' : $pendingCount }}</span>
                @endif
                <span class="mobile-bottom-nav-label">Verifikasi</span>
            </a>

            {{-- Tab 3: Dokumen Hadir (Pusat Penerbitan Klaim Entertain) --}}
            <a href="{{ route('filament.admin.resources.attendance-docs.index') }}"
               class="mobile-bottom-nav-item" :class="{ 'is-active': currentPath.includes('/admin/attendance-docs') }">
                <svg class="w-6 h-6 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.35 3.836c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m8.9-4.414c.376.023.75.05 1.124.08 1.131.094 1.976 1.057 1.976 2.192V8.25m-12 0h12m-12 0v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V8.25m-12 0H3.375" />
                </svg>
                <span class="mobile-bottom-nav-label">Daftar Hadir</span>
            </a>

            {{-- Tab 4: Nota Balik BBM --}}
            <a href="{{ route('filament.admin.resources.bbm-claims.index') }}"
               class="mobile-bottom-nav-item" :class="{ 'is-active': currentPath.includes('/admin/bbm-claims') }">
                <svg class="w-6 h-6 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" />
                </svg>
                <span class="mobile-bottom-nav-label">Nota Balik BBM</span>
            </a>

        @elseif($user->isExecutiveApprover())
            {{-- ========================================================
               ROLE EXECUTIVE APPROVERS (PAK JEJEN, PAK YOGA, BU NURBAITI, THIA AISYAH)
               ======================================================== --}}
            @php
                $approvalRoute = match (true) {
                    $user->isYoga() => route('filament.admin.resources.yoga-claim-approvals.index'),
                    $user->isNurbaiti() => route('filament.admin.resources.nurbaiti-claim-approvals.index'),
                    $user->isThia() => route('filament.admin.resources.thia-claim-approvals.index'),
                    default => route('filament.admin.resources.jejen-claim-approvals.index'),
                };
                $approvalSegment = match (true) {
                    $user->isYoga() => 'yoga-claim-approvals',
                    $user->isNurbaiti() => 'nurbaiti-claim-approvals',
                    $user->isThia() => 'thia-claim-approvals',
                    default => 'jejen-claim-approvals',
                };
            @endphp
            {{-- Tab 2: Persetujuan Klaim (Approval) --}}
            <a href="{{ $approvalRoute }}"
               class="mobile-bottom-nav-item" :class="{ 'is-active': currentPath.includes('/admin/' + '{{ $approvalSegment }}') }">
                <svg class="w-6 h-6 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                @if($pendingCount > 0)
                    <span class="mobile-nav-badge">{{ $pendingCount > 99 ? '99+' : $pendingCount }}</span>
                @endif
                <span class="mobile-bottom-nav-label">Approval</span>
            </a>

        @elseif($user->isRgm())
            {{-- ========================================================
               ROLE RGM (REGIONAL GENERAL MANAGER)
               ======================================================== --}}
            {{-- Tab 2: Persetujuan Klaim (Approval RGM) --}}
            <a href="{{ route('filament.admin.resources.rgm-claim-approvals.index') }}"
               class="mobile-bottom-nav-item" :class="{ 'is-active': currentPath.includes('/admin/rgm-claim-approvals') }">
                <svg class="w-6 h-6 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                @if($pendingCount > 0)
                    <span class="mobile-nav-badge">{{ $pendingCount > 99 ? '99+' : $pendingCount }}</span>
                @endif
                <span class="mobile-bottom-nav-label">Approval</span>
            </a>

            {{-- Tab 3: Nota Balik BBM Pribadi --}}
            <a href="{{ route('filament.admin.resources.bbm-claims.index') }}"
               class="mobile-bottom-nav-item" :class="{ 'is-active': currentPath.includes('/admin/bbm-claims') }">
                <svg class="w-6 h-6 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" />
                </svg>
                <span class="mobile-bottom-nav-label">Nota Balik BBM</span>
            </a>

            {{-- Tab 4: Klaim Transport & Entertain (Input Makan & Ultah beserta Daftar Hadir) --}}
            <a href="{{ route('filament.admin.resources.transport-entertain-claims.index') }}"
               class="mobile-bottom-nav-item" :class="{ 'is-active': currentPath.includes('/admin/transport-entertain-claims') }">
                <svg class="w-6 h-6 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                <span class="mobile-bottom-nav-label">Entertain</span>
            </a>

        @elseif($user->isAsm())
            {{-- ========================================================
               ROLE ASM (AREA SALES MANAGER)
               ======================================================== --}}
            {{-- Tab 2: Persetujuan Klaim (Approval ASM) --}}
            <a href="{{ route('filament.admin.resources.asm-claim-approvals.index') }}"
               class="mobile-bottom-nav-item" :class="{ 'is-active': currentPath.includes('/admin/asm-claim-approvals') }">
                <svg class="w-6 h-6 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                @if($pendingCount > 0)
                    <span class="mobile-nav-badge">{{ $pendingCount > 99 ? '99+' : $pendingCount }}</span>
                @endif
                <span class="mobile-bottom-nav-label">Approval</span>
            </a>

            {{-- Tab 3: Nota Balik BBM Pribadi --}}
            <a href="{{ route('filament.admin.resources.bbm-claims.index') }}"
               class="mobile-bottom-nav-item" :class="{ 'is-active': currentPath.includes('/admin/bbm-claims') }">
                <svg class="w-6 h-6 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" />
                </svg>
                <span class="mobile-bottom-nav-label">Nota Balik BBM</span>
            </a>

            {{-- Tab 4: Klaim Transport & Entertain (Input Makan & Ultah beserta Daftar Hadir) --}}
            <a href="{{ route('filament.admin.resources.transport-entertain-claims.index') }}"
               class="mobile-bottom-nav-item" :class="{ 'is-active': currentPath.includes('/admin/transport-entertain-claims') }">
                <svg class="w-6 h-6 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                <span class="mobile-bottom-nav-label">Entertain</span>
            </a>

        @else
            {{-- ========================================================
               ROLE SALES / STAFF LAPANGAN (PEMOHON)
               Khusus Sales: Hanya Klaim Perjalanan Dinas (Perdin) dan Service Motor
               ======================================================== --}}
            {{-- Tab 2: Klaim Perjalanan Dinas (Perdin) --}}
            <a href="{{ route('filament.admin.resources.perdin-claims.index') }}"
               class="mobile-bottom-nav-item" :class="{ 'is-active': currentPath.includes('/admin/perdin-claims') }">
                <svg class="w-6 h-6 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 0 0 .75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 0 0-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0 1 12 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 0 1-.673-.38m0 0A2.18 2.18 0 0 1 3 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 0 1 3.413-.387m7.5 0V5.25A2.25 2.25 0 0 0 13.5 3h-3a2.25 2.25 0 0 0-2.25 2.25v.894m7.5 0a48.667 48.667 0 0 0-7.5 0M12 12.75h.008v.008H12v-.008Z" />
                </svg>
                <span class="mobile-bottom-nav-label">Perdin</span>
            </a>

            {{-- Tab 3: Klaim Service Motor --}}
            <a href="{{ route('filament.admin.resources.vehicle-service-claims.index') }}"
               class="mobile-bottom-nav-item" :class="{ 'is-active': currentPath.includes('/admin/vehicle-service-claims') }">
                <svg class="w-6 h-6 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.05a4.5 4.5 0 0 0 4.486-6.32l-3.276 3.277a1.5 1.5 0 0 1-2.121-2.121l3.276-3.277a4.5 4.5 0 0 0-6.32 4.486c.138.58.114 1.193-.05 1.743" />
                </svg>
                <span class="mobile-bottom-nav-label">Service</span>
            </a>
        @endif

        {{-- 5. MENU LAIN (Buka Sidebar Filament Drawer untuk Akses Seluruh Menu) --}}
        <button type="button"
                x-data="{}"
                x-on:click="if (window.Alpine && window.Alpine.store('sidebar')) { window.Alpine.store('sidebar').isOpen ? window.Alpine.store('sidebar').close() : window.Alpine.store('sidebar').open(); } else { document.querySelector('.fi-topbar-open-sidebar-btn, .fi-topbar-close-sidebar-btn')?.click(); }"
                class="mobile-bottom-nav-item"
                title="Buka Menu Lainnya">
            <svg class="w-6 h-6 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
            </svg>
            <span class="mobile-bottom-nav-label">Menu Lain</span>
        </button>

    </div>
</nav>
