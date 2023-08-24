<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function index(Request $request)
    {
        if ($request->has('user_id')) {
            $user_id = $request->get('user_id');
        } else if (!$request->has('search_in')) {
            $user_id = auth()->user()->id;
        } else {
            $user_id = 'ALL';
        }

        if (!$request->has('task_id')) {

            $array = null;

            if (!$request->has('id')) {
                $ids = Task::findTaskable($request->get('search_for'), $request->get('search_in'));
            } else {
                $ids = [$request->get('id')];
            }

            if (!empty($ids) && count($ids) == 1) {
                $array = Task::getTaskable($ids[0], $request->get('search_in'));
            }

            // TODO - Fix this
            // $tasks = Task::with('taskable', 'assigned_user', 'create_user', 'notes')
            $tasks = Task::with('assigned_user', 'create_user', 'notes')

                ->searchStatus($request->get('status'))
                ->searchTaskable($ids ?? [], $request->get('search_in'))
                ->where('is_deleted', '0')
                ->searchUser($user_id)
                ->searchCreator($request->get('create_user_id'))
                ->orderBy('status')
                ->orderBy('created_at', 'ASC')
                ->get();
        } else {
            $tasks = Task::with('taskable', 'assigned_user', 'create_user', 'notes')
                ->where('id', $request->get('task_id'))
                ->get();

            $array = Task::getTaskable($tasks->first()->taskable_id, $tasks->first()->taskable_type);
        }

        foreach ($tasks as $task) {

            if ($task->msg_read == '0' && $task->assigned_user_id == auth()->user()->id) {
                $task->msg_read = '1';
                $task->save();
            } else if ($task->msg_read == '1') {
                $task->msg_read = '2';
                $task->save();
            }
        }

        return response()->json([
            'tasks' => $tasks,
            'array' => $array
        ]);
    }

    public function searchInOption()
    {
        Task::getTables();
        $data = [];
        foreach (Task::getTables() as $key => $value) {
            $data[] = [
                'value' => $key,
                'label' => $value
            ];
        }
        return $data;
    }
}
