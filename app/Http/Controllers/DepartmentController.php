<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Division;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $department = Department::with(['head', 'division']);

        if ($request->q) $department = $department->where('name', 'LIKE', '%' . $request->q . '%');

        if (isset($request->division))
            $department = $department->where(function ($departments) use ($request) {
                $departments = $departments->whereHas('division', fn ($q) => $q->where('id', $request->division));
            });

        if ($request->perPage == 'all') {
            $department = $department->get();
        } else {
            $department = $department->paginate($request->get('perPage', 10));
        }

        $userPermission = ['departments_create', 'departments_view', 'departments_edit', 'departments_delete'];
        $permission = checkPermission(auth()->user(), $userPermission);

        return [
            'departments' => $department,
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
            'name' => 'required|string|unique:departments',
            'division_id' => 'required|exists:divisions,id',
            'description' => 'required',
        ]);

        Department::create($request->all());

        return response()->json([
            "message" => "Department Created Successfully"
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
        $department = Department::with('division')->whereId($id)->first();
        return response()->json($department, 200);
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
        $role = Department::with('employees')->withCount('employees')->find($id);

        if ($role->employees_count > 0)
            return message("You can't delete Department because of Employees", 401);
        if ($role->delete())
            return message('Role archived successfully');

        return message('Something went wrong', 401);
    }

    public function getDivisions()
    {
        $division = Division::all();
        return response()->json($division, 200);
    }

    public function updateDepartment(Request $request)
    {
        Department::where('id', $request->id)->update(
            [
                'name' => $request->name,
                "division_id" => $request->division_id,
                'head_id' => $request->head_id,
                'description' => $request->description
            ]
        );
        return response()->json(true, 200);
    }
}
