<?php

namespace App\Services\Votaciones;

use App\Support\Votaciones\RutConsulta;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ActualizarVotantesExcel extends PadronConsultaExcel
{
    public const CAMPOS = [
        'nombre' => ['label' => 'Nombre completo', 'columnas' => ['NOMBRE']],
        'tipo_contrato' => ['label' => 'Tipo de contrato', 'columnas' => ['TIPO_DE_CONTRATO']],
        'grupo' => ['label' => 'Grupo', 'columnas' => ['GRUPO']],
        'asignacion' => ['label' => 'Establecimiento de origen y local de votación', 'columnas' => ['RBD', 'RBD_LUGAR_DE_VOTACION']],
    ];

    public static function columnas(array $campos): array
    {
        $columns = ['ID_REGISTRO', 'RUT'];
        foreach (self::CAMPOS as $key => $meta) {
            if (in_array($key, $campos, true)) { $columns = array_merge($columns, $meta['columnas']); }
        }
        return $columns;
    }

    public function leerActualizacion(string $path, array $campos): array
    {
        $this->validarContenedor($path);
        $book = null;
        try {
            $reader = IOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(true)->setReadEmptyCells(false);
            $names = [];
            foreach ($reader->listWorksheetInfo($path) as $info) {
                if ($this->clave($info['worksheetName']) !== 'ACTUALIZAR_PADRON') { continue; }
                if ($info['totalRows'] > 20001 || $info['totalColumns'] > 20) {
                    throw new InvalidArgumentException('La actualización admite hasta 20.000 filas y 20 columnas.');
                }
                $names[] = $info['worksheetName'];
            }
            if (count($names) !== 1) { throw new InvalidArgumentException('Debe existir una única hoja ACTUALIZAR PADRON.'); }
            $reader->setLoadSheetsOnly($names);
            $book = $reader->load($path);
            $rows = $this->filas($book->getSheetByName($names[0]), self::columnas($campos));
            if (! $rows) { throw new InvalidArgumentException('La hoja ACTUALIZAR PADRON debe contener al menos una fila.'); }
            $result = $seen = [];
            foreach ($rows as $number => $row) {
                $item = ['fila' => $number, 'rut' => substr(trim((string) $row['RUT']), 0, 30), 'id' => null, 'valores' => [], 'error' => null];
                try {
                    $rut = RutConsulta::normalizar($row['RUT']);
                    if ($rut === null) { throw new InvalidArgumentException('RUT inválido: incluya el dígito verificador correcto.'); }
                    $item['rut'] = $rut;
                    if (trim((string) $row['ID_REGISTRO']) !== '') {
                        $id = filter_var($row['ID_REGISTRO'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                        if ($id === false) { throw new InvalidArgumentException('ID_REGISTRO debe ser un entero positivo o quedar vacío para un RUT nuevo.'); }
                        $item['id'] = $id;
                    }
                    if (in_array('nombre', $campos, true)) { $item['valores']['nombre'] = $this->texto($row['NOMBRE'], 'NOMBRE'); }
                    if (in_array('tipo_contrato', $campos, true)) { $item['valores']['tipo_contrato'] = $this->texto($row['TIPO_DE_CONTRATO'], 'TIPO_DE_CONTRATO', 150); }
                    if (in_array('grupo', $campos, true)) {
                        $group = trim((string) $row['GRUPO']);
                        if (mb_strlen($group) > 100) { throw new InvalidArgumentException('GRUPO admite hasta 100 caracteres.'); }
                        $item['valores']['grupo'] = $group === '' ? null : $group;
                    }
                    if (in_array('asignacion', $campos, true)) {
                        $item['valores']['rbd'] = $this->rbd($row['RBD'], 'RBD');
                        $item['valores']['rbd_local'] = $this->rbd($row['RBD_LUGAR_DE_VOTACION'], 'RBD_LUGAR_DE_VOTACION');
                    }
                    $key = $item['id'] ? 'id:'.$item['id'] : 'rut:'.$rut;
                    if (isset($seen[$key])) {
                        $result[$seen[$key]]['error'] = 'Identificación repetida en el archivo; conserve una sola fila por registro.';
                        throw new InvalidArgumentException('Identificación repetida en el archivo; conserve una sola fila por registro.');
                    }
                    $seen[$key] = $number;
                } catch (InvalidArgumentException $e) { $item['error'] = $e->getMessage(); }
                $result[$number] = $item;
            }
            return array_values($result);
        } catch (InvalidArgumentException $e) {
            throw $e;
        } catch (\Throwable) {
            throw new InvalidArgumentException('No fue posible leer el Excel de actualización. Verifique su formato .xlsx, sin contraseña.');
        } finally { $book?->disconnectWorksheets(); }
    }
}
