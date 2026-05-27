<?php

namespace Database\Seeders;

use App\Models\Funcionario\Cargo;
use Illuminate\Database\Seeder;

class CargoSeeder extends Seeder
{
    public function run(): void
    {
        $cargos = [
            // Gestão Escolar
            'Diretor(a)',
            'Diretor(a) Adjunto(a)',
            'Vice-Diretor(a)',
            'Vice-Diretor Matutino',
            'Vice-Diretor Vespertino',
            'Vice-Diretor Noturno',
            'Coordenador(a) Pedagógico(a)',
            'Coordenador(a) de Ensino',
            'Coordenador(a) de Turno',
            'Coordenador(a) de Projeto',
            'Supervisor(a) Escolar',
            'Supervisor(a) Pedagógico(a)',
            'Supervisor(a) de Matrícula',
            'Orientador(a) Educacional',
            'Orientador(a) Pedagógico(a)',
            'Especialista de Educação',
            'Professora Dirigente',

            // Docência
            'Professor(a)',
            'Docente I',
            'Docente I – Sala de Acolhimento',
            'Docente I – Biblioteca',
            'Docente I – Sala de Informática',
            'Docente I – Sala de Leitura',
            'Docente I – Sala de Recurso',
            'Docente II',
            'Docente II – Sala de Leitura',
            'Docente II – Sala de Recurso',
            'Docente III',
            'Docente IV',
            'Docente IV – Sala de Leitura',
            'Docente IV – Laboratório de Informática',
            'Docente Readaptado(a)',
            'Professor(a) de Educação Física',
            'Professor(a) de Artes Manuais',
            'Professor(a) de Dança',
            'Professor(a) de Percussão',
            'Professor(a) de Educação Ambiental',
            'Professor(a) de Apoio',
            'Professor(a) – Sala de Leitura',
            'Regente de Turma',
            'Regente de Ensino Profissionalizante',
            'Regente de Banda',

            // Educação Especial e Inclusão
            'Educação Especial – Trabalho Diferenciado',
            'Auxiliar de Desenvolvimento Infantil',
            'Auxiliar de Desenvolvimento Infantil – PNE',
            'Cuidador(a) de Educando com Necessidades Especiais',
            'Tradutor(a) Intérprete de LIBRAS',
            'Mediador(a)',
            'Estimulador(a)',

            // Educação Infantil
            'Educadora de Desenvolvimento Infantil em Creche',
            'Monitor(a) de Creche',
            'Auxiliar de Creche',

            // Apoio Pedagógico
            'Assistente Pedagógico(a)',
            'Assistente de Alfabetização',
            'Auxiliar de Ensino',
            'Auxiliar de Classe',
            'Monitor(a) Docente de Atividades',
            'Monitor(a) de Laboratório',
            'Inspetor(a) Escolar',
            'Inspetor(a) de Disciplina',
            'Inspetor(a) de Alunos',
            'Articulador(a)',
            'Facilitador(a)',
            'Instrutor(a)',
            'Instrutor(a) de Dança',
            'Instrutor(a) de Fanfarra',
            'Instrutor(a) de Música',
            'Instrutor(a) Profissionalizante',
            'Agente Sócio Educativo',
            'Agente Escolar',
            'Agente de Secretaria',
            'Reforço Escolar',
            'Estagiário(a)',

            // Secretaria e Administração Escolar
            'Secretário(a) Escolar',
            'Auxiliar de Secretaria Escolar',
            'Atendente de Matrícula',
            'Assistente Administrativo',
            'Auxiliar Administrativo',
            'Recepcionista',
            'Digitador(a)',
            'Atendente',
            'Chefe de Setor',
            'Chefe de Disciplina',
            'Gerente Administrativo',
            'Auxiliar Financeiro',
            'Tele-Atendente',

            // Saúde Escolar
            'Assistente Social',
            'Psicólogo(a)',
            'Fonoaudiólogo(a)',
            'Fisioterapeuta',
            'Nutricionista',
            'Técnico(a) de Enfermagem',
            'Auxiliar de Enfermagem',

            // Biblioteca
            'Bibliotecário(a)',
            'Auxiliar de Biblioteca',

            // TI e Suporte Técnico
            'Analista de Sistemas',
            'Operador de Equipamentos TIC',
            'Suporte em TI',
            'Técnico de Informática',
            'Técnico Escolar',

            // Serviços Gerais
            'Auxiliar de Serviços Gerais',
            'Auxiliar de Serviços Gerais – Limpeza',
            'Auxiliar de Serviços Gerais – Merenda',
            'Cozinheiro(a)',
            'Copeira',
            'Servente',
            'Porteiro(a)',
            'Vigia',
            'Motorista',

            // Outros
            'Profissional de Atendimento Integrado',
            'Monitor(a)',
            'Comissionado(a)',
        ];

        foreach ($cargos as $nome) {
            Cargo::updateOrCreate(
                ['crg_nome' => $nome],
                ['crg_fl_ativo' => true],
            );
        }
    }
}
