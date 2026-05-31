<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use App\Models\Transaction;
use App\Models\SavingsGoal;
use App\Models\Budget;
use App\Services\AiTipsService;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

Route::redirect('/', '/login');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function (Request $request) {
        $userId = auth()->id();
        
        $pemasukan = Transaction::where('user_id', $userId)
            ->where('type', 'income')
            ->whereMonth('date', now()->month)
            ->sum('amount');
            
        $pengeluaran = Transaction::where('user_id', $userId)
            ->where('type', 'expense')
            ->whereMonth('date', now()->month)
            ->sum('amount');
            
        // Assuming current balance is all-time income - all-time expense
        $totalIncome = Transaction::where('user_id', $userId)->where('type', 'income')->sum('amount');
        $totalExpense = Transaction::where('user_id', $userId)->where('type', 'expense')->sum('amount');
        $saldoSaatIni = $totalIncome - $totalExpense;
        
        $tabungan = SavingsGoal::where('user_id', $userId)->sum('current_amount');
        $targetTabungan = SavingsGoal::where('user_id', $userId)->get();
        $totalBudget = Budget::where('user_id', $userId)
            ->where('period', 'monthly')
            ->sum('amount');
        $budgetUtilization = $totalBudget > 0
            ? min(100, round(($pengeluaran / $totalBudget) * 100))
            : 0;
        
        $recentTransactions = Transaction::where('user_id', $userId)
            ->with('category')
            ->orderBy('date', 'desc')
            ->take(5)
            ->get();
            
        $topExpenses = Transaction::where('user_id', $userId)
            ->where('type', 'expense')
            ->whereMonth('date', now()->month)
            ->select('category_id', DB::raw('SUM(amount) as total'))
            ->groupBy('category_id')
            ->orderByDesc('total')
            ->take(3)
            ->with('category')
            ->get();
            
        // Calculate health score: just a simple formula
        // High savings ratio = good.
        $healthScore = 50;
        if ($pemasukan > 0) {
            $savingsRatio = ($pemasukan - $pengeluaran) / $pemasukan;
            $healthScore = min(100, max(0, 50 + ($savingsRatio * 100)));
        }

        $topExpenseCategory = optional($topExpenses->first()?->category)->name ?? 'pengeluaran terbesar';
        $topExpenseShare = $pengeluaran > 0
            ? round((((float) ($topExpenses->first()->total ?? 0)) / $pengeluaran) * 100)
            : 0;
        $averageGoalProgress = $targetTabungan->count() > 0
            ? round($targetTabungan->avg(function ($goal) {
                if ((float) $goal->target_amount <= 0) {
                    return 0;
                }

                return min(100, (($goal->current_amount / $goal->target_amount) * 100));
            }))
            : 0;
        $goalNearestDeadline = optional(
            $targetTabungan
                ->filter(fn ($goal) => ! empty($goal->target_date))
                ->sortBy('target_date')
                ->first()
        )->name;
        $goalNames = $targetTabungan
            ->pluck('name')
            ->filter()
            ->take(4)
            ->values()
            ->all();
        $budgetCategoryCount = Budget::where('user_id', $userId)->count();
        $recentExpenseCount = Transaction::where('user_id', $userId)
            ->where('type', 'expense')
            ->whereDate('date', '>=', now()->subDays(14)->toDateString())
            ->count();

        $aiContext = [
            'month' => now()->translatedFormat('F Y'),
            'income' => round((float) $pemasukan, 2),
            'expense' => round((float) $pengeluaran, 2),
            'net_cashflow' => round((float) ($pemasukan - $pengeluaran), 2),
            'expense_ratio_percent' => $pemasukan > 0 ? round(($pengeluaran / $pemasukan) * 100) : 0,
            'top_expense_category' => $topExpenseCategory,
            'top_expense_total' => round((float) ($topExpenses->first()->total ?? 0), 2),
            'top_expense_share_percent' => (int) $topExpenseShare,
            'goals_count' => $targetTabungan->count(),
            'average_goal_progress_percent' => (int) $averageGoalProgress,
            'nearest_goal_deadline_name' => $goalNearestDeadline,
            'total_savings' => round((float) $tabungan, 2),
            'total_budget' => round((float) $totalBudget, 2),
            'budget_utilization_percent' => (int) $budgetUtilization,
            'budget_category_count' => (int) $budgetCategoryCount,
            'recent_expense_count_last_14_days' => (int) $recentExpenseCount,
            'user_input_names' => array_values(array_filter(array_unique(array_merge(
                [$topExpenseCategory, (string) $goalNearestDeadline],
                $goalNames
            )))),
        ];

        $aiTipsCacheKey = 'dashboard.ai_tips.' . $userId . '.' . now()->format('Y-m');
        $refreshRequested = $request->boolean('refresh_tips');
        $aiService = app(AiTipsService::class);
        $cachedTips = Cache::get($aiTipsCacheKey, []);
        $cachedTips = is_array($cachedTips) ? $cachedTips : [];
        $aiTips = [];
        $aiTipsError = null;
        $aiStatus = 'ai_live';

        if (! $refreshRequested && ! empty($cachedTips)) {
            $aiTips = $cachedTips;
            $aiStatus = 'ai_cached';
        } else {
            $aiTipsResult = $aiService->generateTipsResult($aiContext, $cachedTips, $refreshRequested);
            $aiTips = is_array($aiTipsResult['tips'] ?? null) ? $aiTipsResult['tips'] : [];

            if (! empty($aiTips)) {
                Cache::put($aiTipsCacheKey, $aiTips, now()->addHours(8));
                $aiStatus = 'ai_live';
            } else {
                $aiTips = ! empty($cachedTips) ? $cachedTips : $aiService->fallbackTips($aiContext);
                $aiStatus = ! empty($cachedTips) ? 'ai_cached_error' : 'fallback_error';
                $aiTipsError = (string) ($aiTipsResult['error'] ?? 'AI provider unavailable');
            }
        }

        return view('dashboard', compact(
            'pemasukan', 
            'pengeluaran', 
            'saldoSaatIni', 
            'tabungan', 
            'recentTransactions',
            'targetTabungan',
            'healthScore',
            'topExpenses',
            'aiTips',
            'aiTipsError',
            'aiStatus'
        ));
    })->name('dashboard');

    Route::view('transactions', 'transactions')->name('transactions.index');
    Route::view('budgets', 'budgets')->name('budgets.index');
    Route::view('savings-goals', 'savings-goals')->name('savings-goals.index');
    Route::get('reports', [\App\Http\Controllers\ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/pdf', [\App\Http\Controllers\ReportController::class, 'exportPdf'])->name('reports.pdf');
    Route::get('reports/excel', [\App\Http\Controllers\ReportController::class, 'exportExcel'])->name('reports.excel');
    Route::get('reports/csv', [\App\Http\Controllers\ReportController::class, 'exportCsv'])->name('reports.csv');
    Route::view('settings', 'settings')->name('settings.index');

    Route::post('notifications/read-all', function () {
        $user = Auth::user();
        if ($user) {
            $user->unreadNotifications->markAsRead();
        }

        return back();
    })->name('notifications.read-all');

    Route::post('notifications/{notification}/read', function (DatabaseNotification $notification) {
        $user = Auth::user();

        abort_unless($user && $notification->notifiable_id === $user->id, 403);

        if ($notification->read_at === null) {
            $notification->markAsRead();
        }

        $actionUrl = $notification->data['action_url'] ?? null;

        return redirect()->to(is_string($actionUrl) && $actionUrl !== '' ? $actionUrl : url()->previous());
    })->name('notifications.read');
});

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';

