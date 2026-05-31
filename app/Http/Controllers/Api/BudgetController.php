<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Budget;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BudgetController extends Controller
{
    /**
     * List user's budgets with spent amount and percentage.
     */
    public function index(): JsonResponse
    {
        $userId = auth()->id();

        $budgets = Budget::where('user_id', $userId)
            ->with('category')
            ->latest()
            ->get()
            ->map(function ($budget) use ($userId) {
                $spent = Transaction::where('user_id', $userId)
                    ->where('type', 'expense')
                    ->where('category_id', $budget->category_id)
                    ->whereMonth('date', now()->month)
                    ->whereYear('date', now()->year)
                    ->sum('amount');

                $percentage = (float) $budget->amount > 0
                    ? min(100, round(($spent / $budget->amount) * 100))
                    : 0;

                return [
                    'id' => $budget->id,
                    'amount' => (float) $budget->amount,
                    'period' => $budget->period,
                    'category_id' => $budget->category_id,
                    'category' => $budget->category ? [
                        'id' => $budget->category->id,
                        'name' => $budget->category->name,
                        'icon' => $budget->category->icon ?? null,
                        'color' => $budget->category->color ?? null,
                    ] : null,
                    'spent' => (float) $spent,
                    'percentage' => (float) $percentage,
                    'created_at' => $budget->created_at,
                    'updated_at' => $budget->updated_at,
                ];
            });

        return response()->json([
            'success' => true,
            'message' => 'Daftar budget berhasil diambil.',
            'data' => $budgets,
        ]);
    }

    /**
     * Create a new budget.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0'],
            'period' => ['sometimes', 'string', 'in:monthly,weekly,yearly'],
            'category_id' => ['required', 'exists:categories,id'],
        ], [
            'amount.required' => 'Jumlah budget wajib diisi.',
            'amount.numeric' => 'Jumlah budget harus berupa angka.',
            'amount.min' => 'Jumlah budget tidak boleh kurang dari 0.',
            'period.in' => 'Periode harus monthly, weekly, atau yearly.',
            'category_id.required' => 'Kategori wajib dipilih.',
            'category_id.exists' => 'Kategori tidak ditemukan.',
        ]);

        $budget = Budget::create([
            'user_id' => auth()->id(),
            'amount' => $validated['amount'],
            'period' => $validated['period'] ?? 'monthly',
            'category_id' => $validated['category_id'],
        ]);

        $budget->load('category');

        return response()->json([
            'success' => true,
            'message' => 'Budget berhasil ditambahkan.',
            'data' => $budget,
        ], 201);
    }

    /**
     * Show a single budget.
     */
    public function show(string $id): JsonResponse
    {
        $budget = Budget::where('user_id', auth()->id())
            ->with('category')
            ->find($id);

        if (! $budget) {
            return response()->json([
                'success' => false,
                'message' => 'Budget tidak ditemukan.',
                'data' => null,
            ], 404);
        }

        $spent = Transaction::where('user_id', auth()->id())
            ->where('type', 'expense')
            ->where('category_id', $budget->category_id)
            ->whereMonth('date', now()->month)
            ->whereYear('date', now()->year)
            ->sum('amount');

        $percentage = (float) $budget->amount > 0
            ? min(100, round(($spent / $budget->amount) * 100))
            : 0;

        return response()->json([
            'success' => true,
            'message' => 'Detail budget berhasil diambil.',
            'data' => [
                'id' => $budget->id,
                'amount' => (float) $budget->amount,
                'period' => $budget->period,
                'category_id' => $budget->category_id,
                'category' => $budget->category ? [
                    'id' => $budget->category->id,
                    'name' => $budget->category->name,
                    'icon' => $budget->category->icon ?? null,
                    'color' => $budget->category->color ?? null,
                ] : null,
                'spent' => (float) $spent,
                'percentage' => (float) $percentage,
                'created_at' => $budget->created_at,
                'updated_at' => $budget->updated_at,
            ],
        ]);
    }

    /**
     * Update a budget.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $budget = Budget::where('user_id', auth()->id())->find($id);

        if (! $budget) {
            return response()->json([
                'success' => false,
                'message' => 'Budget tidak ditemukan.',
                'data' => null,
            ], 404);
        }

        $validated = $request->validate([
            'amount' => ['sometimes', 'required', 'numeric', 'min:0'],
            'period' => ['sometimes', 'string', 'in:monthly,weekly,yearly'],
            'category_id' => ['sometimes', 'required', 'exists:categories,id'],
        ], [
            'amount.required' => 'Jumlah budget wajib diisi.',
            'amount.numeric' => 'Jumlah budget harus berupa angka.',
            'amount.min' => 'Jumlah budget tidak boleh kurang dari 0.',
            'period.in' => 'Periode harus monthly, weekly, atau yearly.',
            'category_id.required' => 'Kategori wajib dipilih.',
            'category_id.exists' => 'Kategori tidak ditemukan.',
        ]);

        $budget->update($validated);
        $budget->load('category');

        return response()->json([
            'success' => true,
            'message' => 'Budget berhasil diperbarui.',
            'data' => $budget,
        ]);
    }

    /**
     * Delete a budget.
     */
    public function destroy(string $id): JsonResponse
    {
        $budget = Budget::where('user_id', auth()->id())->find($id);

        if (! $budget) {
            return response()->json([
                'success' => false,
                'message' => 'Budget tidak ditemukan.',
                'data' => null,
            ], 404);
        }

        $budget->delete();

        return response()->json([
            'success' => true,
            'message' => 'Budget berhasil dihapus.',
            'data' => null,
        ]);
    }
}
