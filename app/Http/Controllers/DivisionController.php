<?php

namespace App\Http\Controllers;

use App\Models\Division;
use Illuminate\Http\Request;
use Spatie\Permission\Contracts\Permission;

class DivisionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $division = Division::with(['head', 'departments']);
        if ($request->q) $division = $division->where('name', 'LIKE', '%' . $request->q . '%');
        $division = $division->paginate($request->get('perPage', 10));

        $userPermission = ['divisions_create', 'divisions_view', 'divisions_edit', 'divisions_delete'];
        $permission = checkPermission(auth()->user(), $userPermission);

        return [
            'divisions' => $division,
            'permission' => $permission
        ];
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:divisions',
            'description' => 'required',
        ]);

        Division::create($request->all());

        return response()->json([
            "message" => "Division Created Successfully"
        ], 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $division = Division::with('departments')->whereId($id)->first();
        return response()->json($division, 200);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $role = Division::with('departments')->withCount('departments')->find($id);

        if ($role->departments_count > 0)
            return message("You can't delete division because of Departments", 401);
        if ($role->delete())
            return message('Division archived successfully', 201);

        return message('Something went wrong', 401);
    }

    public function updateDivision(Request $request)
    {
        Division::where('id', $request->id)->update(
            [
                'name' => $request->name,
                'head_id' => $request->head_id,
                'description' => $request->description
            ]
        );
        return response()->json(true, 200);
    }
}
