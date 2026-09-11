<?php

namespace App\Http\Controllers;

use App\Models\AuditFinding;
use Illuminate\Http\Request;

class FindingController extends Controller
{
    public function index(Request $request)
    {
        $query = AuditFinding::with(['audit.server'])->orderBy('created_at', 'desc');

        if ($request->filled('severity')) {
            $query->where('severity', strtoupper($request->severity));
        }

        if ($request->filled('category')) {
            $query->where('category', strtoupper($request->category));
        }

        if ($request->filled('search')) {
            $s = '%' . $request->search . '%';
            $query->where(function ($q) use ($s) {
                $q->where('title', 'like', $s)
                  ->orWhere('finding_code', 'like', $s)
                  ->orWhere('description', 'like', $s);
            });
        }

        $findings = $query->paginate(20)->withQueryString();
        $categories = AuditFinding::distinct()->pluck('category')->filter()->values();

        return view('findings.index', compact('findings', 'categories'));
    }
}
