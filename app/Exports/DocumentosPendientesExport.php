<?php

namespace App\Exports;

use App\Models\DocumentType;
use App\Models\User;
use App\Support\DocumentReviewSummary;
use App\Support\DocumentRules;
use App\Support\Rut;
use App\Support\RutChile;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentosPendientesExport
{
    public function download(): StreamedResponse
    {
        $types = DocumentType::query()->get();
        $users = User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['postulante', 'funcionario']))
            ->whereHas('documents', fn ($query) => $query->where('status', 'pending'))
            ->with(['postulantProfile.areaDesempeno', 'documents:id,user_id,document_type_id,status,updated_at,created_at'])
            ->lazyById(500);
        $book = $this->workbook($this->rows($users, $types));

        return response()->streamDownload(function () use ($book): void {
            try {
                (new Xlsx($book))->save('php://output');
            } finally {
                $book->disconnectWorksheets();
            }
        }, 'documentos_pendientes_por_rut_'.now()->format('Ymd_His').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    /** Una fila por RUT válido; los identificadores ausentes o inválidos no se fusionan. */
    public function rows(iterable $users, Collection $types): Collection
    {
        $groups = [];
        $freshSince = now()->subHours(72);
        foreach ($users as $user) {
            $summary = DocumentReviewSummary::forUser($user, $types, $freshSince);
            if ($summary['pending_count'] === 0) {
                continue;
            }
            $rut = Rut::normalize($user->rut);
            $body = $rut ? ltrim(substr($rut, 0, -1), '0') : '';
            $valid = $rut && preg_match('/^[0-9]{1,8}[0-9K]$/', $rut)
                && $body !== '' && RutChile::dv((int) $body) === substr($rut, -1);
            $canonical = $valid ? $body.substr($rut, -1) : null;
            $key = $canonical ? 'rut:'.$canonical : 'user:'.$user->id;
            $groups[$key] ??= [
                'rut' => $canonical ? Rut::format($canonical) : ($user->rut ?: 'Sin RUT'),
                'names' => [], 'emails' => [], 'user_ids' => [], 'documents' => [],
                'pending_count' => 0, 'oldest_pending_at' => null,
                'notes' => $valid ? '' : 'RUT ausente o inválido: registro separado por ID de usuario.',
            ];
            $row = &$groups[$key];
            $row['names'][] = $user->display_name ?: ($user->email ?: 'Usuario '.$user->id);
            $row['emails'][] = (string) $user->email;
            $row['user_ids'][] = $user->id;
            $row['pending_count'] += $summary['pending_count'];
            $oldest = $summary['oldest_pending_at'];
            if ($oldest && (!$row['oldest_pending_at'] || $oldest->lt($row['oldest_pending_at']))) {
                $row['oldest_pending_at'] = $oldest;
            }
            $visible = DocumentRules::visibleTypesFromCatalog($user, $types)->keyBy('id');
            foreach ($user->documents->where('status', 'pending')->whereIn('document_type_id', $visible->keys()) as $doc) {
                $label = $visible[$doc->document_type_id]->label;
                $row['documents'][$label] = ($row['documents'][$label] ?? 0) + 1;
            }
            unset($row);
        }

        return collect($groups)->map(function (array $row) {
            foreach (['names', 'emails', 'user_ids'] as $field) {
                $row[$field] = collect($row[$field])->unique()->sort()->implode("\n");
            }
            ksort($row['documents']);
            $row['documents'] = collect($row['documents'])->map(fn ($count, $label) => $label.' ('.$count.')')->implode("\n");

            return $row;
        })->sortBy(fn ($row) => [$row['oldest_pending_at']?->getTimestamp() ?? PHP_INT_MAX, $row['rut'], $row['user_ids']])->values();
    }

    public function workbook(Collection $rows): Spreadsheet
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle('Pendientes por RUT');
        $sheet->fromArray([['RUT', 'Nombre completo', 'Correo', 'ID de usuarios', 'Documentos pendientes', 'Pendiente más antiguo', 'Documentos por revisar', 'Observaciones']], null, 'A1');
        $sheet->getStyle('A1:H1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E78']],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(32);
        foreach ($rows as $index => $row) {
            $line = $index + 2;
            // Los datos de usuarios se escriben como texto, nunca como fórmulas.
            foreach (['A' => 'rut', 'B' => 'names', 'C' => 'emails', 'D' => 'user_ids', 'G' => 'documents', 'H' => 'notes'] as $column => $field) {
                $sheet->setCellValueExplicit($column.$line, (string) $row[$field], DataType::TYPE_STRING);
            }
            $sheet->setCellValue('E'.$line, $row['pending_count']);
            if ($row['oldest_pending_at']) {
                $sheet->setCellValue('F'.$line, Date::PHPToExcel($row['oldest_pending_at']->copy()->setTimezone(cl_chile_timezone())));
                $sheet->getStyle('F'.$line)->getNumberFormat()->setFormatCode('dd-mm-yyyy hh:mm');
            } else {
                $sheet->setCellValueExplicit('F'.$line, 'Sin fecha registrada', DataType::TYPE_STRING);
            }
        }
        $last = max(1, $rows->count() + 1);
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:H'.$last);
        $sheet->getStyle('A1:H'.$last)->getAlignment()->setWrapText(true)->setVertical('top');
        foreach (['A' => 19, 'B' => 40, 'C' => 38, 'D' => 16, 'E' => 18, 'F' => 24, 'G' => 55, 'H' => 45] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }
        $sheet->getComment('F1')->getText()->createTextRun('Última carga o actualización del pendiente más antiguo, en horario de Chile. Si no existe updated_at se usa created_at. Sin fecha: al final.');

        return $book;
    }
}
