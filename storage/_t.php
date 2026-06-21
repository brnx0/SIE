<?php
auth()->loginUsingId(1);
DB::beginTransaction();
$t = DB::table('edu_turma')->where('tur_id',1)->first();
App\Models\Diario\NotaManual::create(['nmn_tur_id'=>1,'nmn_dis_id'=>2,'nmn_uni_id'=>5,'nmn_aln_id'=>1,'nmn_anl_id'=>$t->tur_anl_id,'nmn_esc_id'=>$t->tur_esc_id,'nmn_tipo'=>'numerica','nmn_media'=>7]);
$ctrl = new App\Http\Controllers\Secretaria\LancamentoManualController();
$mk = function($p){ $r = Illuminate\Http\Request::create('/x','POST',$p); $r->setUserResolver(fn()=>App\Models\User::find(1)); return $r; };
try { $ctrl->salvar($mk(['tur_id'=>1,'dis_id'=>2,'uni_id'=>3,'aln_id'=>1,'tipo'=>'conceitual','media'=>5])); echo "FALHOU: deveria bloquear\n"; }
catch (\Illuminate\Validation\ValidationException $e) { echo 'bloqueado OK: '.implode(' ', $e->validator->errors()->all())."\n"; }
try { $ctrl->salvar($mk(['tur_id'=>1,'dis_id'=>2,'uni_id'=>3,'aln_id'=>1,'tipo'=>'numerica','media'=>8])); echo "numerica outra unidade: OK\n"; }
catch (\Throwable $e) { echo 'ERRO numerica: '.$e->getMessage()."\n"; }
DB::rollBack();
