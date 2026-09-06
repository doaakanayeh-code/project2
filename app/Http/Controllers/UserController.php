<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\UsersExport; 
use App\Imports\UsersImport; 

class UserController extends Controller
{
    public function export()
    {
        return Excel::download(new UsersExport, 'users.xlsx');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls|max:1024'
        ]);

        Excel::import(new UsersImport, $request->file('file'));

        return response()->json(['message' => 'Users imported successfully'], 200);
    }
}