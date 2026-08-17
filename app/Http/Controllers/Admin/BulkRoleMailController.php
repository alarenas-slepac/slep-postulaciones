<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendBulkRoleMail;
use App\Models\User;
use App\Support\SlepUiRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class BulkRoleMailController extends Controller
{
    public function index(): View
    {
        $roles = Role::query()
            ->withCount([
                'users as recipients_count' => fn ($query) => $query
                    ->whereNotNull('email')
                    ->where('email', '!=', '')
                    ->whereNotNull('email_verified_at'),
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role) => [
                'name' => $role->name,
                'label' => SlepUiRegistry::roleLabel($role->name),
                'recipients_count' => (int) $role->recipients_count,
            ]);

        return view('admin.bulk-role-mail.index', compact('roles'));
    }

    public function send(Request $request): RedirectResponse
    {
        $availableRoles = Role::query()->pluck('name')->all();

        $data = $request->validate([
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['required', 'string', Rule::in($availableRoles)],
            'subject' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:20000'],
            'confirm' => ['accepted'],
        ]);

        $roles = collect($data['roles'])->map(fn ($role) => trim((string) $role))->filter()->unique()->values()->all();

        $recipientIds = $this->recipientsQuery($roles)
            ->pluck('users.id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($recipientIds->isEmpty()) {
            return back()->withInput()->withErrors(['roles' => 'Los roles seleccionados no tienen usuarios con un correo válido y verificado.']);
        }

        foreach ($recipientIds as $userId) {
            SendBulkRoleMail::dispatch(
                $userId,
                trim((string) $data['subject']),
                trim((string) $data['body']),
                (int) $request->user()->id,
                $roles
            );
        }

        return redirect()->route('admin.bulk-role-mail.index')->with('success', sprintf(
            'Se programaron %d correo(s) para %d rol(es). Cada usuario recibirá un solo correo aunque tenga más de uno de los roles seleccionados.',
            $recipientIds->count(),
            count($roles)
        ));
    }

    private function recipientsQuery(array $roles): Builder
    {
        return User::query()
            ->select('users.id')
            ->whereNotNull('users.email')
            ->where('users.email', '!=', '')
            ->whereNotNull('users.email_verified_at')
            ->whereHas('roles', fn ($query) => $query->whereIn('name', $roles))
            ->orderBy('users.id');
    }
}
