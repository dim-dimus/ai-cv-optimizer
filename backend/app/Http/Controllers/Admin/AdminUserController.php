<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminUserResource;
use App\Models\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminUserController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $users = User::query()
            ->withCount('analyses')
            ->with('resume:id,user_id')
            ->latest()
            ->paginate(25);

        return AdminUserResource::collection($users);
    }
}
