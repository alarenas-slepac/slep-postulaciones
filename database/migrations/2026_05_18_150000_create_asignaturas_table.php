<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('asignaturas')) {
            Schema::create('asignaturas', function (Blueprint $table) {
                $table->id();
                $table->string('nombre', 180)->unique();
                $table->string('codigo', 80)->unique();
                $table->string('nivel_educativo', 80)->nullable()->index();
                $table->string('area', 120)->nullable()->index();
                $table->string('tipo_asignatura', 60)->index();
                $table->boolean('es_oficial')->default(true)->index();
                $table->boolean('activo')->default(true)->index();
                $table->text('observacion')->nullable();
                $table->timestamps();
            });
        }

        $now = now();
        $rows = [
            ['Lenguaje y Comunicación','LENG_COM','Educación Básica','Lenguaje','obligatoria','Asignatura obligatoria de Educación Básica.'],
            ['Lengua y Literatura','LENG_LIT','Educación Media','Lengua y Literatura','obligatoria','Plan común de Formación General.'],
            ['Matemática','MAT','Transversal','Matemática','obligatoria','Asignatura obligatoria transversal.'],
            ['Historia, Geografía y Ciencias Sociales','HIST_GEO_CS','Transversal','Historia, Geografía y Ciencias Sociales','plan_comun_electivo','Asignatura del Plan Común de Formación General Electivo.'],
            ['Artes Visuales','ART_VIS','Educación Básica','Artes','obligatoria','Asignatura obligatoria de Educación Básica.'],
            ['Música','MUS','Educación Básica','Artes','obligatoria','Asignatura obligatoria de Educación Básica.'],
            ['Artes Visuales y Música','ART_VIS_MUS','Educación Básica','Artes','obligatoria','Bloque de 7° y 8° básico.'],
            ['Artes','ARTES','Transversal','Artes','plan_comun_electivo','Asignatura del Plan Común de Formación General Electivo.'],
            ['Educación Física y Salud','EDFIS_SALUD','Transversal','Educación Física y Salud','plan_comun_electivo','Asignatura del Plan Común de Formación General Electivo.'],
            ['Orientación','ORIENT','Transversal','Orientación','obligatoria','Asignatura obligatoria.'],
            ['Tecnología','TEC','Transversal','Tecnología','obligatoria','Asignatura obligatoria.'],
            ['Religión','REL','Transversal','Religión','plan_comun_electivo','Debe ofrecerse obligatoriamente, optativa para estudiantes y familias.'],
            ['Ciencias Naturales','CIEN_NAT','Educación Básica','Ciencias','obligatoria','Asignatura obligatoria de Educación Básica.'],
            ['Idioma Extranjero: Inglés','ING_BAS','Educación Básica','Inglés','obligatoria','Asignatura obligatoria en Educación Básica.'],
            ['Inglés','ING','Educación Media','Inglés','obligatoria','Plan común de Formación General.'],
            ['Educación Ciudadana','ED_CIUD','Educación Media','Educación Ciudadana','obligatoria','Plan común de Formación General.'],
            ['Filosofía','FIL','Educación Media','Filosofía','obligatoria','Plan común de Formación General.'],
            ['Ciencias para la Ciudadanía','CIEN_CIUD','Educación Media','Ciencias','obligatoria','Plan común de Formación General.'],
            ['Lengua Indígena','LENG_IND','Educación Básica','Lengua Indígena','obligatoria','Plan 7° y 8° básico con Sector Lengua Indígena.'],
            ['Horas de libre disposición','HLD','Transversal','Libre disposición','libre_disposicion','Bloque de horas configurable por establecimiento.'],
            ['Plan Diferenciado Humanístico-Científico','PD_HC','Educación Media','Plan Diferenciado','plan_diferenciado_hc','Bloque de profundización HC.'],
            ['Plan Diferenciado Técnico-Profesional','PD_TP','Educación Media','Plan Diferenciado','plan_diferenciado_tp','Bloque de especialidad TP.'],
            ['Plan Diferenciado Artístico','PD_ART','Educación Media','Plan Diferenciado','plan_diferenciado_artistico','Bloque de especialidad artística.'],

            ['Taller de Literatura','HC_TALLER_LIT','Educación Media','Área A - Lengua y Literatura','plan_diferenciado_hc','Profundización HC.'],
            ['Lectura y Escritura Especializadas','HC_LECT_ESCR','Educación Media','Área A - Lengua y Literatura','plan_diferenciado_hc','Profundización HC.'],
            ['Participación y Argumentación en Democracia','HC_PART_ARG','Educación Media','Área A - Lengua y Literatura','plan_diferenciado_hc','Profundización HC.'],
            ['Estética','HC_ESTETICA','Educación Media','Área A - Filosofía','plan_diferenciado_hc','Profundización HC.'],
            ['Filosofía Política','HC_FIL_POL','Educación Media','Área A - Filosofía','plan_diferenciado_hc','Profundización HC.'],
            ['Seminario de Filosofía','HC_SEM_FIL','Educación Media','Área A - Filosofía','plan_diferenciado_hc','Profundización HC.'],
            ['Comprensión Histórica del Presente','HC_COMP_HIST','Educación Media','Área A - Historia, Geografía y Ciencias Sociales','plan_diferenciado_hc','Profundización HC.'],
            ['Geografía, Territorio y Desafíos Socioambientales','HC_GEO_TERR','Educación Media','Área A - Historia, Geografía y Ciencias Sociales','plan_diferenciado_hc','Profundización HC.'],
            ['Economía y Sociedad','HC_ECON_SOC','Educación Media','Área A - Historia, Geografía y Ciencias Sociales','plan_diferenciado_hc','Profundización HC.'],
            ['Límites, Derivadas e Integrales','HC_LIM_DER_INT','Educación Media','Área B - Matemática','plan_diferenciado_hc','Profundización HC.'],
            ['Probabilidades y Estadística Descriptiva e Inferencial','HC_PROB_EST','Educación Media','Área B - Matemática','plan_diferenciado_hc','Profundización HC.'],
            ['Pensamiento Computacional y Programación','HC_PENS_COMP','Educación Media','Área B - Matemática','plan_diferenciado_hc','Profundización HC.'],
            ['Biología de los Ecosistemas','HC_BIO_ECO','Educación Media','Área B - Ciencias','plan_diferenciado_hc','Profundización HC.'],
            ['Biología Celular y Molecular','HC_BIO_CEL','Educación Media','Área B - Ciencias','plan_diferenciado_hc','Profundización HC.'],
            ['Ciencias de la Salud','HC_CIEN_SALUD','Educación Media','Área B - Ciencias','plan_diferenciado_hc','Profundización HC.'],
            ['Física','HC_FISICA','Educación Media','Área B - Ciencias','plan_diferenciado_hc','Profundización HC.'],
            ['Química','HC_QUIMICA','Educación Media','Área B - Ciencias','plan_diferenciado_hc','Profundización HC.'],
            ['Artes Visuales, Audiovisuales y Multimediales','HC_ART_VIS_MULTI','Educación Media','Área C - Artes','plan_diferenciado_hc','Profundización HC.'],
            ['Creación y Composición Musical','HC_CRE_COMP_MUS','Educación Media','Área C - Artes','plan_diferenciado_hc','Profundización HC.'],
            ['Interpretación y Creación en Danza','HC_DANZA','Educación Media','Área C - Artes','plan_diferenciado_hc','Profundización HC.'],
            ['Interpretación y Creación en Teatro','HC_TEATRO','Educación Media','Área C - Artes','plan_diferenciado_hc','Profundización HC.'],
            ['Diseño y Arquitectura','HC_DIS_ARQ','Educación Media','Área C - Artes','plan_diferenciado_hc','Profundización HC.'],
            ['Promoción de Estilos de Vida Activos y Saludables','HC_ESTILOS_VIDA','Educación Media','Área C - Educación Física y Salud','plan_diferenciado_hc','Profundización HC.'],
            ['Ciencias del Ejercicio Físico y Deportivo','HC_EJERCICIO','Educación Media','Área C - Educación Física y Salud','plan_diferenciado_hc','Profundización HC.'],
            ['Expresión Corporal','HC_EXP_CORP','Educación Media','Área C - Educación Física y Salud','plan_diferenciado_hc','Profundización HC.'],

            ['Forestal','TP_FORESTAL','Educación Media','MADERERO','plan_diferenciado_tp','Especialidad TP.'],
            ['Muebles y Terminaciones en Madera','TP_MUEBLES_MADERA','Educación Media','MADERERO','plan_diferenciado_tp','Especialidad TP.'],
            ['Agropecuaria','TP_AGROPECUARIA','Educación Media','AGROPECUARIO','plan_diferenciado_tp','Especialidad TP.'],
            ['Elaboración Industrial de Alimentos','TP_ALIMENTOS','Educación Media','ALIMENTACION','plan_diferenciado_tp','Especialidad TP.'],
            ['Gastronomía','TP_GASTRONOMIA','Educación Media','ALIMENTACION','plan_diferenciado_tp','Especialidad TP.'],
            ['Construcción','TP_CONSTRUCCION','Educación Media','CONSTRUCCION','plan_diferenciado_tp','Especialidad TP.'],
            ['Refrigeración y Climatización','TP_REFRIGERACION','Educación Media','CONSTRUCCION','plan_diferenciado_tp','Especialidad TP.'],
            ['Instalaciones Sanitarias','TP_INST_SANITARIAS','Educación Media','CONSTRUCCION','plan_diferenciado_tp','Especialidad TP.'],
            ['Montaje Industrial','TP_MONTAJE_IND','Educación Media','CONSTRUCCION','plan_diferenciado_tp','Especialidad TP.'],
            ['Mecánica Automotriz','TP_MEC_AUTOMOTRIZ','Educación Media','METALMECANICA','plan_diferenciado_tp','Especialidad TP.'],
            ['Mecánica Industrial','TP_MEC_INDUSTRIAL','Educación Media','METALMECANICA','plan_diferenciado_tp','Especialidad TP.'],
            ['Mecánica de Mantenimiento de Aeronaves','TP_MEC_AERONAVES','Educación Media','METALMECANICA','plan_diferenciado_tp','Especialidad TP.'],
            ['Construcciones Metálicas','TP_CONST_METALICAS','Educación Media','METALMECANICA','plan_diferenciado_tp','Especialidad TP.'],
            ['Electricidad','TP_ELECTRICIDAD','Educación Media','ELECTRICIDAD','plan_diferenciado_tp','Especialidad TP.'],
            ['Electrónica','TP_ELECTRONICA','Educación Media','ELECTRICIDAD','plan_diferenciado_tp','Especialidad TP.'],
            ['Acuicultura','TP_ACUICULTURA','Educación Media','MARITIMO','plan_diferenciado_tp','Especialidad TP.'],
            ['Pesquería','TP_PESQUERIA','Educación Media','MARITIMO','plan_diferenciado_tp','Especialidad TP.'],
            ['Tripulación de Naves Mercantes y Especiales','TP_TRIP_NAVES','Educación Media','MARITIMO','plan_diferenciado_tp','Especialidad TP.'],
            ['Operaciones Portuarias','TP_OP_PORTUARIAS','Educación Media','MARITIMO','plan_diferenciado_tp','Especialidad TP.'],
            ['Explotación Minera','TP_EXP_MINERA','Educación Media','MINERO','plan_diferenciado_tp','Especialidad TP.'],
            ['Metalurgia Extractiva','TP_MET_EXTRACTIVA','Educación Media','MINERO','plan_diferenciado_tp','Especialidad TP.'],
            ['Asistencia en Geología','TP_GEOLOGIA','Educación Media','MINERO','plan_diferenciado_tp','Especialidad TP.'],
            ['Gráfica','TP_GRAFICA','Educación Media','GRAFICO','plan_diferenciado_tp','Especialidad TP.'],
            ['Dibujo Técnico','TP_DIB_TECNICO','Educación Media','GRAFICO','plan_diferenciado_tp','Especialidad TP.'],
            ['Vestuario y Confección Textil','TP_VESTUARIO','Educación Media','CONFECCION','plan_diferenciado_tp','Especialidad TP.'],
            ['Administración','TP_ADMINISTRACION','Educación Media','ADMINISTRACION','plan_diferenciado_tp','Especialidad TP.'],
            ['Contabilidad','TP_CONTABILIDAD','Educación Media','ADMINISTRACION','plan_diferenciado_tp','Especialidad TP.'],
            ['Atención de Párvulos','TP_PARVULOS','Educación Media','SALUD Y EDUCACION','plan_diferenciado_tp','Especialidad TP.'],
            ['Atención de Enfermería','TP_ENFERMERIA','Educación Media','SALUD Y EDUCACION','plan_diferenciado_tp','Especialidad TP.'],
            ['Química Industrial','TP_QUIMICA_IND','Educación Media','QUIMICA E INDUSTRIA','plan_diferenciado_tp','Especialidad TP.'],
            ['Conectividad y Redes','TP_CONECT_REDES','Educación Media','TECNOLOGIA Y COMUNICACIONES','plan_diferenciado_tp','Especialidad TP.'],
            ['Telecomunicaciones','TP_TELECOM','Educación Media','TECNOLOGIA Y COMUNICACIONES','plan_diferenciado_tp','Especialidad TP.'],
            ['Programación','TP_PROGRAMACION','Educación Media','TECNOLOGIA Y COMUNICACIONES','plan_diferenciado_tp','Especialidad TP.'],
            ['Servicios de Hotelería','TP_HOTELERIA','Educación Media','HOTELERIA Y TURISMO','plan_diferenciado_tp','Especialidad TP.'],
            ['Servicios de Turismo','TP_TURISMO','Educación Media','HOTELERIA Y TURISMO','plan_diferenciado_tp','Especialidad TP.'],
        ];

        foreach ($rows as $row) {
            DB::table('asignaturas')->updateOrInsert(
                ['codigo' => $row[1]],
                [
                    'nombre' => $row[0],
                    'nivel_educativo' => $row[2],
                    'area' => $row[3],
                    'tipo_asignatura' => $row[4],
                    'es_oficial' => true,
                    'activo' => true,
                    'observacion' => $row[5],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('asignaturas');
    }
};
