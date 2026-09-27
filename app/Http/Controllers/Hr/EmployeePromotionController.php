<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StoreEmployeePromotionRequest;
use App\Models\Guard;
use App\Models\Position;
use App\Services\EmployeePromotionService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use InvalidArgumentException;

class EmployeePromotionController extends Controller
{
    public function __construct(private EmployeePromotionService $promotions) {}

    public function store(StoreEmployeePromotionRequest $request, Guard $guard): RedirectResponse
    {
        $position = Position::query()->findOrFail($request->validated('position_id'));

        try {
            $promotion = $this->promotions->schedule(
                $guard,
                $position,
                (float) $request->validated('new_salary'),
                Carbon::parse($request->validated('effective_from')),
                (string) $request->validated('reason'),
                $request->user(),
                $request->validated('reference'),
                $request->validated('remarks'),
                $request->filled('region_id') ? (int) $request->validated('region_id') : null,
                $request->file('document'),
            );
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['effective_from' => $exception->getMessage()]);
        }

        $guard->refresh();
        $target = $promotion->status === 'applied' && $guard->linkedStaff
            ? redirect()->route('staff.show', $guard->linkedStaff)
            : redirect()->route('guards.show', $guard);

        return $target->with('status', $promotion->status === 'applied'
            ? 'Promotion applied. The same employee record was kept.'
            : 'Promotion scheduled. The employee stays on the guard roster until '.$promotion->effective_from->format('d M Y').'.');
    }
}
