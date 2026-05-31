<?php
namespace App\Livewire;

use Livewire\Component;
use App\Models\Transaction;
use App\Models\Category;
use App\Models\Budget;
use App\Notifications\BudgetUsageAlert;
use Illuminate\Support\Facades\Auth;

class TransactionManager extends Component
{
    public $description, $amount, $date, $type = "expense", $category_id;

    public function mount()
    {
        $this->date = date('Y-m-d');
    }

    public function save()
    {
        Transaction::create([
            'amount' => $this->amount,
            'type' => $this->type,
            'category_id' => $this->category_id,
            'description' => $this->description,
            'date' => $this->date,
            'user_id' => auth()->id(),
        ]);

        $this->notifyBudgetUsageIfNeeded();
        $this->reset(["description", "amount"]);
    }

    public function delete($id)
    {
        Transaction::where("user_id", Auth::id())->where("id", $id)->delete();
    }

    public function render()
    {
        return view("livewire.transaction-manager", [
            "transactions" => Transaction::where("user_id", Auth::id())->with('category')->latest()->get(),
            "categories" => Category::all()
        ]);
    }

    private function notifyBudgetUsageIfNeeded(): void
    {
        if ($this->type !== 'expense') {
            return;
        }

        $user = Auth::user();
        if (! $user) {
            return;
        }

        $totalBudget = Budget::where('user_id', $user->id)
            ->where('period', 'monthly')
            ->sum('amount');

        if ((float) $totalBudget <= 0) {
            return;
        }

        $monthExpense = Transaction::where('user_id', $user->id)
            ->where('type', 'expense')
            ->whereMonth('date', now()->month)
            ->whereYear('date', now()->year)
            ->sum('amount');

        $utilizationPercent = ($monthExpense / $totalBudget) * 100;
        if ($utilizationPercent < 80) {
            return;
        }

        $alreadyNotifiedToday = $user->unreadNotifications()
            ->where('type', BudgetUsageAlert::class)
            ->whereDate('created_at', now()->toDateString())
            ->exists();

        if ($alreadyNotifiedToday) {
            return;
        }

        $user->notify(new BudgetUsageAlert(
            utilizationPercent: (float) $utilizationPercent,
            spent: (float) $monthExpense,
            budget: (float) $totalBudget
        ));
    }
}
