<?php

namespace App\Http\Controllers;

use App\Models\Manufacture;
use App\Models\User;
use Illuminate\Http\Request;

class ManufactureController extends Controller
{
    public function index(Request $request)
    {
        $manufactures = Manufacture::query();
        if ($request->q) {
            $manufactures = $manufactures->where('name', 'like', '%' . $request->q . '%');
        }
        return $manufactures->orderBy('id', 'desc')->paginate($request->get('perPage', 10));
    }

    public function show(string $id)
    {
        return Manufacture::find($id);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
        ]);

        $data = $request->only([
            'name',
            'description',
        ]);
        try {
            Manufacture::create($data);
            return response()->json([
                'message' => 'Manufacture created successfully!',
                'status' => 201,
                'data' => []
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'status' => 403,
                'data' => []
            ], 403);
        }
    }

    public function update(Request $request, string $id)
    {
        $request->validate([
            'name' => 'required',
        ]);
        try {
            $manufacture = Manufacture::find($id);
            if (!$manufacture) {
                return response()->json([
                    'message' => 'Manufacture not found!',
                    'status' => 203,
                    'data' => []
                ], 203);
            }
            $data = $request->only([
                'name',
                'description',
            ]);
            $manufacture->update($data);
            return response()->json([
                'message' => 'Manufacture update successfully!',
                'status' => 201,
                'data' => []
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'status' => 403,
                'data' => []
            ], 403);
        }
    }

    public function destroy(string $id)
    {
        $data = Manufacture::find($id);
        if ($data) {
            $data->delete();
            return response()->json([
                'message' => 'Manufacture delete successfully!',
                'status' => 201,
                'data' => []
            ], 201);
        }
        return response()->json([
            'message' => "Manufacture didn't found!",
            'status' => 203,
            'data' => []
        ], 203);
    }

    public function getAccess(string $id)
    {
        $user = User::get();
        $user->transform(function ($item) use ($id) {
            return [
                'id' => $item['id'],
                'name' => $item['name'],
                'access' => in_array($id, $item['permit_manufactures'] ?? []),
            ];
        });
        return $user;
    }

    public function updateAccess(Request $request)
    {
        $manufacture = Manufacture::find($request->id);
        if (!$manufacture) return response()->json([
            'message' => "Manufacture didn't found!",
            'status' => 203,
            'data' => []
        ], 203);
        foreach ($request->data as $key => $value) {
            $user = User::find($value['id']);
            $permit = $user['permit_manufactures'] ?? [];
            if ($value['access'] && !in_array($manufacture->id, $permit)) {
                $permit[] = $manufacture->id;
            } elseif (!$value['access'] && in_array($manufacture->id, $permit)) {
                unset($permit[array_search($manufacture->id, $permit)]);
                (array)$permit;
            }
            $user->permit_manufactures = $permit;
            $user->save();
        }
        return response()->json([
            'message' => "Permission update successfull!",
            'status' => 201,
            'data' => []
        ], 201);
    }
}
