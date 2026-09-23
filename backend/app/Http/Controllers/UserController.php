<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/** Manage Users (Admin only — see the `admin` middleware on these routes). */
class UserController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return UserResource::collection(User::with('assignedPerson:id,name')->orderBy('name')->get());
    }

    public function show(User $user): UserResource
    {
        return new UserResource($user->load('assignedPerson:id,name'));
    }

    public function store(UserRequest $request): JsonResponse
    {
        $user = User::create($this->attributes($request));

        return (new UserResource($user->load('assignedPerson:id,name')))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UserRequest $request, User $user): UserResource
    {
        $user->update($this->attributes($request));

        return new UserResource($user->load('assignedPerson:id,name'));
    }

    public function destroy(Request $request, User $user): Response
    {
        abort_if($request->user()->id === $user->id, 422, 'You cannot delete your own account.');
        abort_if(
            User::where('assigned_person_id', $user->id)->exists(),
            422,
            'This person is still linked as the Assigned Person for another user account. Change that account first.',
        );

        $user->delete();

        return response()->noContent();
    }

    /**
     * Validated input, ready for create/update. A blank `password` on edit
     * means "keep the current one". `assigned_person_id` is not part of this
     * form at all — it's left untouched on update, and unset (null) on
     * create — so the reminder-assignment/visibility scoping that already
     * depends on it for existing accounts is unaffected.
     *
     * @return array<string, mixed>
     */
    private function attributes(UserRequest $request): array
    {
        $data = $request->validated();

        if (empty($data['password'])) {
            unset($data['password']);
        }

        return $data;
    }
}
