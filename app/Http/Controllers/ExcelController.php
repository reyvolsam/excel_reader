<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Excel;

class ExcelController extends Controller
{
    private $res = [];
    private $request;

    function __construct(Request $request)
    {
        $this->request = $request;
        $this->res['message'] = '';
        $this->status_code = 204;

        date_default_timezone_set('America/Mexico_City');
    }//__construct()

    public function loadExcel()
    {
        try{

            $files = [];
            //PRO
            //$dir = '../../wp-content/uploads/2019/08/';
            //TEST
            $dir = '../../wordpress/wp-content/uploads/2019/08/';
            foreach (glob($dir."*.xlsx") as $file){ 
                $files[] = $file; 
            } 

            foreach ($files AS $vf) {
                if(file_exists($vf) ){
                    $sin_dir = explode("/", $vf);
                    $porciones_file = explode("_", $sin_dir[count($sin_dir)-1]);

                    if( count($porciones_file) > 0 ){
                        $curse = \DB::table('curses')->where('name', $porciones_file[0])->first();

                        if($curse){
                            Excel::load($vf, function ($reader) use ($curse, $vf) {
                                $correct_structure = false;
                                $reader->takeRows(1);
                
                                if( array_key_exists('folio', $reader->toArray()[0]) && array_key_exists('nombre', $reader->toArray()[0]) && array_key_exists('fecha', $reader->toArray()[0]) ){
                                    $correct_structure = true;
                                }
                
                                if($correct_structure == true){
                                    if( count( $reader->toArray() ) > 0 ){
                                        Excel::filter('chunk')->load($vf)->chunk(250, function($results) use ($curse){
                                            if( count($results) > 0 ){
                                                foreach ($results as $kap => $row) {
                                                    if($row['folio'] != NULL && $row['nombre'] != NULL && $row['fecha'] != NULL ){
                                                        if(preg_match("/^(0[1-9]|1[0-2])(\/|-)([0-2][0-9]|3[0-1])(\/|-)(\d{4})$/", $row['fecha'])){

                                                            $f = explode('/', $row['fecha']);
                                                            $converted_date = $f[2].'-'.$f[1].'-'.$f[0];

                                                            $repeated = \DB::table('folios')->where([
                                                                    'folio' => $row['folio'],
                                                                    'name' => $row['nombre'],
                                                                    'date' => $converted_date
                                                                ])->count();
                                                            
                                                            if($repeated == 0){
                                                                \DB::table('folios')->insert([
                                                                    'curse_id'  => $curse->id,
                                                                    'folio'     => $row['folio'],
                                                                    'name'      => $row['nombre'],
                                                                    'date'      => $converted_date,
                                                                    'created_at' => date('Y-m-d H:i:s')
                                                                ]);
                                                            }
                                                        } else {
                                                            \DB::table('logger')->insert(['description' => 'Folio: '.$row['folio'].'. Nombre: '.$row['nombre'].'. Fecha: '.$row['fecha'].' con formato de fecha incorrecto. No se proceso este registro.', 'created_at' => date('Y-m-d H:i:s')]);
                                                        }
                                                    }
                                                }
                                            }
                                        });
                                    } else {
                                        \DB::table('logger')->insert(['description' => 'El archivo '.$vf.' no tiene datos. Archivo no procesado.', 'created_at' => date('Y-m-d H:i:s')]);
                                    }
                                } else {
                                    \DB::table('logger')->insert(['description' => 'La estructura del archivo '.$vf.' es incorrecta o no tiene datos. Archivo no procesado.', 'created_at' => date('Y-m-d H:i:s')]);
                                }
                            });
                        } else {
                            \DB::table('logger')->insert(['description' => 'El nombre del curso en el archivo '.$vf.' no fue encontrado. Archivo no procesado.', 'created_at' => date('Y-m-d H:i:s')]);
                        }
                    } else {
                        \DB::table('logger')->insert(['description' => 'El archivo '.$vf.' no tiene el formato esperado. Archivo no procesado.', 'created_at' => date('Y-m-d H:i:s')]);
                    }

                    $sin_dir = explode("/", $vf);
                    $file_name = explode(".", $sin_dir[count($sin_dir)-1]);

                    unlink($vf);
                    \DB::table('wp_posts')->where('post_type', 'attachment')->where('post_title', $file_name[0])->delete();
                } else {
                    \DB::table('logger')->insert(['description' => 'El archivo '.$vf.' no existe.', 'created_at' => date('Y-m-d H:i:s')]);
                }
            }

            $this->res['datos'] = $files;
            $this->res['message'] = 'Éxito';
            $this->status_code = 200;
        } catch(\Exception $e){
            \DB::table('logger')->insert(['description' => $e, 'created_at' => date('Y-m-d H:i:s')]);
            $this->res['msg'] = 'Error en el sistema.'.$e;
            $this->status_code = 500;
        }
        return response()->json($this->res, $this->status_code);
    }//

}////
