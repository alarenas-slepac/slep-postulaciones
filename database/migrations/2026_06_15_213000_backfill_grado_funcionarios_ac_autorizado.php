<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tablas = [
            'funcionario_ac_autorizado',
            'funcionarios_ac_autorizadas',
            'funcionarios_ac_autorizados',
        ];

        $registros = [
            ['run' => '17444817', 'dv' => '5', 'grado' => '10'],
            ['run' => '13109895', 'dv' => '2', 'grado' => '12'],
            ['run' => '12036808', 'dv' => '7', 'grado' => '10'],
            ['run' => '12447993', 'dv' => '2', 'grado' => '9'],
            ['run' => '19598386', 'dv' => '0', 'grado' => '7'],
            ['run' => '17347359', 'dv' => '1', 'grado' => '9'],
            ['run' => '13951498', 'dv' => 'K', 'grado' => '10'],
            ['run' => '14059098', 'dv' => '3', 'grado' => '13'],
            ['run' => '17221119', 'dv' => '4', 'grado' => '10'],
            ['run' => '17317104', 'dv' => '8', 'grado' => '10'],
            ['run' => '10873320', 'dv' => '9', 'grado' => '5'],
            ['run' => '12796465', 'dv' => '3', 'grado' => '15'],
            ['run' => '18107957', 'dv' => '6', 'grado' => '15'],
            ['run' => '18988754', 'dv' => 'K', 'grado' => '15'],
            ['run' => '12924798', 'dv' => '3', 'grado' => '16'],
            ['run' => '15888883', 'dv' => '1', 'grado' => '8'],
            ['run' => '14392850', 'dv' => '0', 'grado' => '15'],
            ['run' => '12681610', 'dv' => '3', 'grado' => '10'],
            ['run' => '14212802', 'dv' => '0', 'grado' => '11'],
            ['run' => '16292701', 'dv' => '9', 'grado' => '6'],
            ['run' => '15881932', 'dv' => '5', 'grado' => '9'],
            ['run' => '13107032', 'dv' => '2', 'grado' => '11'],
            ['run' => '18134965', 'dv' => '4', 'grado' => '17'],
            ['run' => '19725669', 'dv' => '9', 'grado' => '15'],
            ['run' => '10086566', 'dv' => '1', 'grado' => '22'],
            ['run' => '15885404', 'dv' => 'K', 'grado' => '10'],
            ['run' => '16818414', 'dv' => 'K', 'grado' => '10'],
            ['run' => '12131198', 'dv' => '4', 'grado' => '17'],
            ['run' => '9895242', 'dv' => 'K', 'grado' => '15'],
            ['run' => '15944801', 'dv' => '0', 'grado' => '8'],
            ['run' => '10957486', 'dv' => '4', 'grado' => '16'],
            ['run' => '9860498', 'dv' => '7', 'grado' => '4'],
            ['run' => '19509674', 'dv' => '0', 'grado' => '12'],
            ['run' => '16818550', 'dv' => '2', 'grado' => '11'],
            ['run' => '19579423', 'dv' => '5', 'grado' => '8'],
            ['run' => '16152269', 'dv' => '4', 'grado' => '8'],
            ['run' => '12362874', 'dv' => '8', 'grado' => '9'],
            ['run' => '16783755', 'dv' => '7', 'grado' => '7'],
            ['run' => '7257207', 'dv' => '6', 'grado' => '10'],
            ['run' => '15188701', 'dv' => '5', 'grado' => '11'],
            ['run' => '15176054', 'dv' => '6', 'grado' => '10'],
            ['run' => '13959615', 'dv' => '3', 'grado' => '10'],
            ['run' => '10783635', 'dv' => '7', 'grado' => '17'],
            ['run' => '20785037', 'dv' => '3', 'grado' => '15'],
            ['run' => '7553698', 'dv' => '4', 'grado' => '6'],
            ['run' => '18109550', 'dv' => '4', 'grado' => '6'],
            ['run' => '14549638', 'dv' => '1', 'grado' => '12'],
            ['run' => '17574138', 'dv' => '0', 'grado' => '10'],
            ['run' => '10670824', 'dv' => 'K', 'grado' => '13'],
            ['run' => '13141547', 'dv' => '8', 'grado' => '15'],
            ['run' => '19092155', 'dv' => '7', 'grado' => '8'],
            ['run' => '18704417', 'dv' => '0', 'grado' => '10'],
            ['run' => '12969848', 'dv' => '9', 'grado' => '5'],
            ['run' => '19907265', 'dv' => 'K', 'grado' => '15'],
            ['run' => '18230513', 'dv' => '8', 'grado' => '9'],
            ['run' => '17036284', 'dv' => '5', 'grado' => '16'],
            ['run' => '18810155', 'dv' => '0', 'grado' => '15'],
            ['run' => '17170211', 'dv' => '9', 'grado' => '7'],
            ['run' => '18118293', 'dv' => '8', 'grado' => '9'],
            ['run' => '16490447', 'dv' => '4', 'grado' => '5'],
            ['run' => '12702461', 'dv' => '8', 'grado' => '14'],
            ['run' => '15659849', 'dv' => '6', 'grado' => '9'],
            ['run' => '17444784', 'dv' => '5', 'grado' => '10'],
            ['run' => '13512808', 'dv' => '2', 'grado' => '20'],
            ['run' => '15945124', 'dv' => '0', 'grado' => '8'],
            ['run' => '18415329', 'dv' => '7', 'grado' => '13'],
            ['run' => '13108856', 'dv' => '6', 'grado' => '8'],
            ['run' => '19599106', 'dv' => '5', 'grado' => '13'],
            ['run' => '15615564', 'dv' => '0', 'grado' => '7'],
            ['run' => '12553994', 'dv' => '7', 'grado' => '16'],
            ['run' => '19150755', 'dv' => 'K', 'grado' => '9'],
            ['run' => '16598723', 'dv' => '3', 'grado' => '6'],
            ['run' => '16818471', 'dv' => '9', 'grado' => '13'],
            ['run' => '13799274', 'dv' => '4', 'grado' => '9'],
            ['run' => '19855292', 'dv' => '5', 'grado' => '15'],
            ['run' => '9527572', 'dv' => '9', 'grado' => '9'],
            ['run' => '12705011', 'dv' => '2', 'grado' => '18'],
            ['run' => '18067597', 'dv' => '3', 'grado' => '11'],
            ['run' => '9107399', 'dv' => '4', 'grado' => '21'],
            ['run' => '22156675', 'dv' => '0', 'grado' => '17'],
            ['run' => '13959422', 'dv' => '3', 'grado' => '12'],
            ['run' => '15173790', 'dv' => '0', 'grado' => '7'],
            ['run' => '18135685', 'dv' => '5', 'grado' => '10'],
            ['run' => '12072492', 'dv' => '4', 'grado' => '13'],
            ['run' => '19662569', 'dv' => '0', 'grado' => '9'],
            ['run' => '13141621', 'dv' => '0', 'grado' => '19'],
            ['run' => '13635015', 'dv' => '3', 'grado' => '10'],
            ['run' => '15517266', 'dv' => '5', 'grado' => '9'],
            ['run' => '15615690', 'dv' => '6', 'grado' => '9'],
            ['run' => '19107886', 'dv' => '1', 'grado' => '14'],
            ['run' => '16010746', 'dv' => '4', 'grado' => '9'],
            ['run' => '14354782', 'dv' => '5', 'grado' => '6'],
        ];

        foreach ($tablas as $tabla) {
            if (! Schema::hasTable($tabla)) {
                continue;
            }

            if (! Schema::hasColumn($tabla, 'grado')) {
                continue;
            }

            $columnasIdentificacion = [
                'run' => Schema::hasColumn($tabla, 'run'),
                'rut' => Schema::hasColumn($tabla, 'rut'),
                'rut_normalizado' => Schema::hasColumn($tabla, 'rut_normalizado'),
            ];

            foreach ($registros as $registro) {
                $rutNormalizado = $registro['run'] . strtoupper($registro['dv']);

                $query = DB::table($tabla)
                    ->where(function ($q) use ($registro, $rutNormalizado, $columnasIdentificacion) {
                        if ($columnasIdentificacion['run']) {
                            $q->orWhere('run', $registro['run']);
                        }

                        if ($columnasIdentificacion['rut']) {
                            $q->orWhere('rut', $registro['run']);
                        }

                        if ($columnasIdentificacion['rut_normalizado']) {
                            $q->orWhere('rut_normalizado', $rutNormalizado);
                        }
                    });

                if (($columnasIdentificacion['run'] || $columnasIdentificacion['rut']) && Schema::hasColumn($tabla, 'dv')) {
                    $query->where(function ($q) use ($registro, $rutNormalizado, $columnasIdentificacion) {
                        $q->where('dv', $registro['dv']);

                        if ($columnasIdentificacion['rut_normalizado']) {
                            $q->orWhere('rut_normalizado', $rutNormalizado);
                        }
                    });
                } elseif (($columnasIdentificacion['run'] || $columnasIdentificacion['rut']) && Schema::hasColumn($tabla, 'digito_verificador')) {
                    $query->where(function ($q) use ($registro, $rutNormalizado, $columnasIdentificacion) {
                        $q->where('digito_verificador', $registro['dv']);

                        if ($columnasIdentificacion['rut_normalizado']) {
                            $q->orWhere('rut_normalizado', $rutNormalizado);
                        }
                    });
                }

                $query->update(['grado' => $registro['grado']]);
            }
        }
    }

    public function down(): void
    {
        // No se revierte el valor de grado para evitar pérdida de información cargada o corregida manualmente.
    }
};
