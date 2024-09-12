<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskNote;
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
            // TODO - Fix this
            // $tasks = Task::with('taskable', 'assigned_user', 'create_user', 'notes')
            $tasks = Task::with('assigned_user', 'create_user', 'notes')
                ->where('id', $request->get('task_id'))
                ->get();

            // TODO - Fix this
            // $array = Task::getTaskable($tasks->first()->taskable_id, $tasks->first()->taskable_type);
            $array = [];
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

    public function store(Request $request)
    {
        if (!$request->user || $request->user == 'undefined') {
            return response()->json([
                'message' => 'Please select a user for this task',
                'status' => 203
            ], 203);
        }

        if (!$request->has('id')) {
            $ids = Task::findTaskable($request->get('associate_with'), $request->get('model'));
            if ($ids) {
                $id = $ids[0];
                $model = $request->get('model');
            } else {
                $id = null;
                $model = null;
            }
        } else {
            $id = $request->get('id');
            $model = $request->get('model');
        }

        $task = Task::new(
            $request->get('text'),
            $request->get('user'),
            $model,
            $id,
            $request->get('close_event'),
            $request->get('previous_task'),
            null,
            $request->get('due_date')
        );
        if ($task) {
            $this->save_attachment($task->id, $request->file('attach'));
        }

        return response()->json([
            'message' => 'Task Created',
            'params' => [
                'task_id' => $task->id
            ],
            'status' => 201
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $task = Task::find($id);

        if (!$task) {
            return;
        }

        $note = null;

        if ($request->has('note_text')) {
            $note = new TaskNote;
            $note->task_id = $id;
            $note->text = $request->get('note_text');
            $note->user_id = auth()->user()->id;
            $note->save();

            $task->msg_read = '0';
            $task->save();
        }

        $saved = $this->save_attachment($id, $request->file('attach'));

        if ($saved) {
            $task->msg_read = '0';
            $task->save();
        }

        if ($request->reassign) {

            if ($request->get('reassign') != $task->assigned_user_id) {

                $new_user = User::find($request->get('reassign'));

                $note_text = '  ( Reassigned to '  . $new_user->username . ' )';

                if (!$note) {
                    $note = new TaskNote;
                    $note->task_id = $id;
                    $note->user_id = auth()->user()->id;
                }

                $note->text .= $note_text;
                $note->save();

                $task->assigned_user_id = $request->get('reassign');
                $task->msg_read = '0';
                $task->save();
            }
        }
        return response()->json([
            'message' => 'Task Updated',
            'params' => [
                'task_id' => $task->id
            ],
            'status' => 201
        ], 201);
    }

    private function save_attachment($id, $file)
    {
        if ($file == null) {
            return false;
        }
        $filename = $id . date("_Ymd_His.", strtotime('now')) . $file->getClientOriginalExtension();
        if (move_uploaded_file($file, base_path() . '/public_html/assets/attachments/' . $filename)) {
            $note = new TaskNote;
            $note->task_id = $id;
            $note->text = $filename;
            $note->ext = strtolower($file->getClientOriginalExtension());
            $note->user_id = auth()->user()->id;
            $note->save();
            return true;
        }
        return false;
    }

    public function delete($id)
    {
        $task = Task::find($id);

        if ($task) {
            $task->status = 'C';
            $task->save();

            $note = new TaskNote();
            $note->task_id = $id;
            $note->text = 'Task Closed';
            $note->user_id = auth()->user()->id;
            $note->save();

            return response()->json([
                'message' => 'Task Closed',
                'status' => 201
            ], 201);
        } else {
            return response()->json([
                'message' => 'Task Not Found',
                'status' => 203
            ], 203);
        }
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

    public function userTasks()
    {
        $unread = Task::where('assigned_user_id', auth()->user()->id)
            ->where('status', 'O')
            ->where('msg_read', '=', '0')
            ->count();

        $all_msg = Task::where('assigned_user_id', auth()->user()->id)
            ->where('status', 'O')
            ->count();

        return response()->json([
            'unread' => $unread,
            'all_msg' => $all_msg
        ]);
    }

    public function tasksDue ()
    {
        $tasks = Task::where('status', 'O')
            ->where('due_date', '<=', date("Y-m-d"))
            ->update(['msg_read' => '0']);

        info('Due Tasks Updated');
        return;
    }
}
