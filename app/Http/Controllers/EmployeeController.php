<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\Employee;
use App\Models\Position;
use RealRashid\SweetAlert\Facades\Alert;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\EmployeesExport;
use PDF;
use Illuminate\Support\Str;

class EmployeeController extends Controller
{
    public function exportPdf()
    {
        $employees = Employee::all();

        $pdf = PDF::loadView('employee.export_pdf', compact('employees'));

        return $pdf->download('employees.pdf');
    }

    public function exportExcel()
    {
        return Excel::download(new EmployeesExport, 'employees.xlsx');
    }

    public function getData(Request $request)
    {
        if ($request->ajax()) {
            $employees = Employee::with('position');
            return datatables()->of($employees)
                ->addIndexColumn()
                ->addColumn('actions', function ($employee) {
                    return view('employee.actions', compact('employee'));
                })
                ->toJson();
        }
    }

    public function index()
    {
        $pageTitle = 'Employee List';
        $positions = Position::all();

        return view('employee.index', [
            'pageTitle' => $pageTitle,
            'positions' => $positions,
        ]);
    }

    public function create()
    {
        $pageTitle = 'Create Employee';
        $positions = Position::all();

        return view('employee.create', compact('pageTitle', 'positions'));
    }

    public function store(Request $request)
    {
        $messages = [
            'required' => ':Attribute harus diisi',
            'email' => 'Isi :attribute dengan format yang benar',
            'numeric' => 'Isi :attribute dengan angka',
            'email.unique' => 'Email ini sudah digunakan. Harap gunakan email lain.',
            'cv.required' => 'File CV harus diunggah.',
        ];

        $validator = Validator::make($request->all(), [
            'firstName' => 'required',
            'lastName' => 'required',
            'email' => 'required|email|unique:employees,email',
            'age' => 'required|numeric',
            'cv' => 'required|file|mimes:pdf,doc,docx|max:2048',
        ], $messages);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $file = $request->file('cv');
        $employee = new Employee();
        $employee->firstname = $request->firstName;
        $employee->lastname = $request->lastName;
        $employee->email = $request->email;
        $employee->age = $request->age;
        $employee->position_id = $request->position;

        if ($file) {
            $employee->original_filename = $file->getClientOriginalName();
            $employee->encrypted_filename = $file->store('public/files');
        }

        $employee->save();

        Alert::success('Added Successfully', 'Employee Data Added Successfully.');

        return redirect()->route('employees.index');
    }

    public function show(string $id)
    {
        $pageTitle = 'Employee Detail';
        $employee = Employee::findOrFail($id);

        return view('employee.show', compact('pageTitle', 'employee'));
    }

    public function edit(string $id)
    {
        $pageTitle = 'Edit Employee';
        $positions = Position::all();
        $employee = Employee::findOrFail($id);

        return view('employee.edit', compact('pageTitle', 'positions', 'employee'));
    }

    public function update(Request $request, string $id)
    {
        $messages = [
            'required' => ':Attribute harus diisi.',
            'email' => 'Isi :attribute dengan format yang benar',
            'numeric' => 'Isi :attribute dengan angka',
            'cv.mimes' => 'File harus dalam format: pdf, doc, docx.',
            'cv.max' => 'Ukuran file tidak boleh lebih dari 2MB.',
        ];

        $validator = Validator::make($request->all(), [
            'firstName' => 'required',
            'lastName' => 'required',
            'email' => 'required|email|unique:employees,email,' . $id,
            'age' => 'required|numeric',
            'position' => 'required|exists:positions,id',
            'cv' => 'nullable|file|mimes:pdf,doc,docx|max:2048',
        ], $messages);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $employee = Employee::findOrFail($id);

        $employee->firstname = $request->firstName;
        $employee->lastname = $request->lastName;
        $employee->email = $request->email;
        $employee->age = $request->age;
        $employee->position_id = $request->position;

        if ($request->hasFile('cv')) {
            if ($employee->encrypted_filename) {
                Storage::delete($employee->encrypted_filename);
            }

            $employee->original_filename = $request->file('cv')->getClientOriginalName();
            $employee->encrypted_filename = $request->file('cv')->store('public/files');
        }

        $employee->save();

        Alert::success('Updated Successfully', 'Employee Data Updated Successfully.');

        return redirect()->route('employees.index');
    }

    public function destroy(string $id)
    {
        $employee = Employee::findOrFail($id);

        if ($employee->encrypted_filename) {
            Storage::delete($employee->encrypted_filename);
        }

        $employee->delete();

        Alert::success('Deleted Successfully', 'Employee Data Deleted Successfully.');

        return redirect()->route('employees.index');
    }

    public function downloadFile($id)
    {
        $employee = Employee::findOrFail($id);

        if (Storage::exists($employee->encrypted_filename)) {
            $filename = Str::lower($employee->firstname . '_' . $employee->lastname . '_cv.pdf');
            return Storage::download($employee->encrypted_filename, $filename);
        }

        return redirect()->back()->with('error', 'File not found.');
    }
}
