<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;

class RolePermissionController extends Controller
{
    public function index()
    {
        $roles = Role::with(['permissions', 'users'])->get();
        $permissions = Permission::all()->groupBy('module');

        return view('roles.index', compact('roles', 'permissions'));
    }

    public function show(Role $role)
    {
        $role->load('permissions');
        $allPermissions = Permission::all()->groupBy('module');
        $rolePermissionIds = $role->permissions->pluck('id')->toArray();

        return view('roles.show', compact('role', 'allPermissions', 'rolePermissionIds'));
    }

    public function updatePermissions(Request $request, Role $role)
    {
        if ($role->name === 'root') {
            return back()->with('info', 'Role Root memiliki semua permission secara otomatis.');
        }

        $permissionIds = $request->input('permissions', []);
        $role->permissions()->sync($permissionIds);

        return back()->with('success', "Hak akses / permission untuk role [{$role->display_name}] berhasil diperbarui!");
    }

    public function storePermission(Request $request)
    {
        $validated = $request->validate([
            'module' => 'required|string|max:50',
            'action' => 'required|string|max:50',
            'display_name' => 'required|string|max:100',
            'description' => 'nullable|string',
        ]);

        $name = strtolower($validated['module']) . '.' . strtolower($validated['action']);

        if (Permission::where('name', $name)->exists()) {
            return back()->with('error', "Permission [{$name}] sudah ada di sistem.");
        }

        $permission = Permission::create([
            'name' => $name,
            'module' => strtolower($validated['module']),
            'action' => strtolower($validated['action']),
            'display_name' => $validated['display_name'],
            'description' => $validated['description'],
        ]);

        $rootRole = Role::where('name', 'root')->first();
        if ($rootRole) {
            $rootRole->permissions()->syncWithoutDetaching([$permission->id]);
        }

        return back()->with('success', "Permission baru [{$name}] berhasil didaftarkan ke sistem!");
    }

    public function updateRole(Request $request, Role $role)
    {
        $validated = $request->validate([
            'display_name' => 'required|string|max:100',
            'description' => 'nullable|string',
        ]);

        $role->update($validated);
        return back()->with('success', "Data Role [{$role->name}] berhasil diperbarui!");
    }
}
