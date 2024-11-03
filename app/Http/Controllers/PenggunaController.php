<?php

namespace App\Http\Controllers;

use App\Models\JenisPenggunaModel;
use App\Models\PenggunaModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Barryvdh\DomPDF\Facade\Pdf;

class PenggunaController extends Controller
{
    // Display the main page for pengguna
    public function index()
    {
        $breadcrumb = (object)[
            'title' => 'Daftar Pengguna',
            'list' => ['Home', 'Pengguna'],
        ];

        $page = (object)[
            'title' => 'Daftar pengguna yang terdaftar dalam sistem'
        ];

        $activeMenu = 'pengguna'; // Active menu set to pengguna
        $jenis_pengguna = JenisPenggunaModel::all(); // Get jenis_pengguna data for filtering
        return view('pengguna.index', ['breadcrumb' => $breadcrumb, 'page' => $page, 'activeMenu' => $activeMenu, 'jenis_pengguna' => $jenis_pengguna]);
    }

    public function list(Request $request)
    {
        $pengguna = PenggunaModel::select('id_pengguna', 'username', 'nama', 'email', 'NIP', 'id_jenis_pengguna')
            ->with('jenisPengguna');

        if ($request->id_jenis_pengguna) {
            $pengguna->where('id_jenis_pengguna', $request->id_jenis_pengguna);
        }
        return DataTables::of($pengguna)
            ->addIndexColumn()
            ->addColumn('aksi', function ($pengguna) {
                $btn = '<button onclick="modalAction(\'' . url('/pengguna/' . $pengguna->id_pengguna . '/show_ajax') . '\')" class="btn btn-info btn-sm">Detail</button> ';
                $btn .= '<button onclick="modalAction(\'' . url('/pengguna/' . $pengguna->id_pengguna . '/edit_ajax') . '\')" class="btn btn-warning btn-sm">Edit</button> ';
                $btn .= '<button onclick="modalAction(\'' . url('/pengguna/' . $pengguna->id_pengguna . '/delete_ajax') . '\')" class="btn btn-danger btn-sm">Hapus</button> ';
                return $btn;
            })
            ->rawColumns(['aksi'])
            ->make(true);
    }

    public function create()
    {
        $breadcrumb = (object)[
            'title' => 'Tambah Pengguna',
            'list' => ['Home', 'Pengguna', 'Tambah']
        ];

        $page = (object)[
            'title' => 'Tambah Pengguna Baru'
        ];

        $jenis_pengguna = JenisPenggunaModel::all();
        $activeMenu = 'pengguna';

        return view('pengguna.create', ['breadcrumb' => $breadcrumb, 'page' => $page, 'jenis_pengguna' => $jenis_pengguna, 'activeMenu' => $activeMenu]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'username' => 'required|string|min:3|unique:pengguna,username',
            'nama' => 'required|string|max:100',
            'email' => 'required|email|unique:pengguna,email',
            'NIP' => 'required|string|unique:pengguna,NIP',
            'password' => 'required|min:5',
            'id_jenis_pengguna' => 'required|integer',
        ]);

        PenggunaModel::create([
            'username' => $request->username,
            'nama' => $request->nama,
            'email' => $request->email,
            'NIP' => $request->NIP,
            'password' => bcrypt($request->password),
            'id_jenis_pengguna' => $request->id_jenis_pengguna
        ]);

        return redirect('/pengguna')->with('success', 'Data pengguna berhasil disimpan');
    }

    public function show(string $id)
    {
        $pengguna = PenggunaModel::with('jenisPengguna')->find($id);

        $breadcrumb = (object)[
            'title' => 'Detail Pengguna',
            'list' => ['Home', 'Pengguna', 'Detail']
        ];

        $page = (object)[
            'title' => 'Detail Pengguna'
        ];

        $activeMenu = 'pengguna';
        return view('pengguna.show', ['breadcrumb' => $breadcrumb, 'page' => $page, 'pengguna' => $pengguna, 'activeMenu' => $activeMenu]);
    }

    public function show_ajax(string $id)
    {
        $pengguna = PenggunaModel::with('jenisPengguna')->find($id);
        $page = (object)[
            'title' => 'Detail Pengguna'
        ];
        return view('pengguna.show_ajax', ['pengguna' => $pengguna, 'page' => $page]);
    }

    public function edit_ajax(string $id)
    {
        $pengguna = PenggunaModel::find($id);
        $jenis_pengguna = JenisPenggunaModel::select('id_jenis_pengguna', 'nama_jenis_pengguna')->get();
        return view('pengguna.edit_ajax', ['pengguna' => $pengguna, 'jenis_pengguna' => $jenis_pengguna]);
    }

    public function update_ajax(Request $request, $id)
    {
        if ($request->ajax() || $request->wantsJson()) {
            $rules = [
                'id_jenis_pengguna' => 'required|integer',
                'username' => 'required|max:20|unique:pengguna,username,' . $id . ',id_pengguna',
                'nama' => 'required|max:100',
                'email' => 'required|email|unique:pengguna,email,' . $id . ',id_pengguna',
                'NIP' => 'required|string|unique:pengguna,NIP,' . $id . ',id_pengguna',
                'password' => 'nullable|min:6|max:20'
            ];
            $validator = Validator::make($request->all(), $rules);
            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validasi Gagal',
                    'msgField' => $validator->errors(),
                ]);
            }
            $check = PenggunaModel::find($id);
            if ($check) {
                if (!$request->filled('password')) {
                    $request->request->remove('password');
                }
                $check->update($request->all());
                return response()->json([
                    'status' => true,
                    'message' => 'Data berhasil diupdate'
                ]);
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'Data tidak ditemukan'
                ]);
            }
        }
        return redirect('/');
    }

    public function update(Request $request, string $id)
    {
        $request->validate([
            'username' => 'required|string|min:3|unique:pengguna,username,' . $id . ',id_pengguna',
            'nama' => 'required|string|max:100',
            'email' => 'required|email|unique:pengguna,email,' . $id . ',id_pengguna',
            'NIP' => 'required|string|unique:pengguna,NIP,' . $id . ',id_pengguna',
            'password' => 'nullable|min:5',
            'id_jenis_pengguna' => 'required|integer'
        ]);

        $pengguna = PenggunaModel::find($id);
        
        $pengguna->update([
            'username' => $request->username,
            'nama' => $request->nama,
            'email' => $request->email,
            'NIP' => $request->NIP,
            'password' => $request->password ? bcrypt($request->password) : $pengguna->password,
            'id_jenis_pengguna' => $request->id_jenis_pengguna
        ]);
        return redirect('/pengguna')->with('success', 'Data pengguna berhasil diubah');
    }

    public function destroy(string $id)
    {
        $check = PenggunaModel::find($id);
        if (!$check) {
            return redirect('/pengguna')->with('error', 'Data pengguna tidak ditemukan');
        }

        try {
            PenggunaModel::destroy($id);
            return redirect('/pengguna')->with('success', 'Data pengguna berhasil dihapus');
        } catch (\Illuminate\Database\QueryException $e) {
            return redirect('/pengguna')->with('error', 'Data pengguna gagal dihapus karena terkait dengan tabel lain');
        }
    }

    public function confirm_ajax(string $id)
    {
        $pengguna = PenggunaModel::find($id);
        return view('pengguna.confirm_ajax', ['pengguna' => $pengguna]);
    }

    public function delete_ajax(Request $request, $id)
    {
        if ($request->ajax() || $request->wantsJson()) {
            $pengguna = PenggunaModel::find($id);
            if ($pengguna) {
                $pengguna->delete();
                return response()->json([
                    'status' => true,
                    'message' => 'Data berhasil dihapus'
                ]);
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'Data tidak ditemukan'
                ]);
            }
        }
        return redirect('/');
    }

    public function create_ajax()
    {
        $jenis_pengguna = JenisPenggunaModel::select('id_jenis_pengguna', 'nama_jenis_pengguna')->get();
        return view('pengguna.create_ajax')->with('jenis_pengguna', $jenis_pengguna);
    }

    public function store_ajax(Request $request)
    {
        if ($request->ajax() || $request->wantsJson()) {
            $rules = [
                'id_jenis_pengguna' => 'required|integer',
                'username' => 'required|string|min:3|unique:pengguna,username',
                'nama' => 'required|string|max:100',
                'email' => 'required|email|unique:pengguna,email',
                'NIP' => 'required|string|unique:pengguna,NIP',
                'password' => 'required|min:6'
            ];
            $validator = Validator::make($request->all(), $rules);
            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validasi Gagal',
                    'msgField' => $validator->errors(),
                ]);
            }
            PenggunaModel::create([
                'id_jenis_pengguna' => $request->id_jenis_pengguna,
                'username' => $request->username,
                'nama' => $request->nama,
                'email' => $request->email,
                'NIP' => $request->NIP,
                'password' => Hash::make($request->password),
            ]);
            return response()->json([
                'status' => true,
                'message' => 'Data berhasil ditambahkan'
            ]);
        }
        return redirect('/');
    }
}