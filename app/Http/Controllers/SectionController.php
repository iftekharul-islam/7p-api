<?php

namespace App\Http\Controllers;

use App\Models\Section;
use Illuminate\Http\Request;

class SectionController extends Controller
{
    public function index(Request $request)
    {
        $vendors = Section::query();
        if ($request->q) {
            $vendors = $vendors->where('section_name', 'like', '%' . $request->q . '%');
        }
        $vendors = $vendors->orderBy('id', 'desc')->paginate($request->get('perPage', 10));
        return $vendors;
    }

    public function show(string $id)
    {
        return Section::find($id);
    }

    public function store(Request $request)
    {
        $request->validate([
            'section_name' => 'required',
        ]);

        $data = $request->only([
            'section_name',
            'summaries',
            'start_finish',
            'same_user',
            'print_label',
            'inventory',
            'inv_control',
        ]);
        try {
            Section::create($data);
            return response()->json([
                'message' => 'Section created successfully!',
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
            'section_name' => 'required',
        ]);
        try {
            $section = Section::find($id);
            if (!$section) {
                return response()->json([
                    'message' => 'Section not found!',
                    'status' => 203,
                    'data' => []
                ], 203);
            }
            $data = $request->only([
                'section_name',
                'summaries',
                'start_finish',
                'same_user',
                'print_label',
                'inventory',
                'inv_control',
            ]);
            $section->update($data);
            return response()->json([
                'message' => 'Section update successfully!',
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
        $data = Section::find($id);
        if ($data) {
            $data->delete();
            return response()->json([
                'message' => 'Section delete successfully!',
                'status' => 201,
                'data' => []
            ], 201);
        }
        return response()->json([
            'message' => "Section didn't found!",
            'status' => 203,
            'data' => []
        ], 203);
    }

    public function sectionOption()
    {
        $section = Section::where('is_deleted', '0')->get();
        $section->transform(function ($item) {
            return [
                'value' => $item['id'],
                'label' => $item['section_name'],
                'data' => $item
            ];
        });
        return $section;
    }
}
