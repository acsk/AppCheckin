import test from 'node:test';
import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
const source=await readFile(new URL('../src/utils/recordesOficiais.js',import.meta.url),'utf8');
const {officialDefinition,officialRecord,rankRecords,definitionStats,parseMark,timeInput,parseTime,fmtMetric}=await import(`data:text/javascript;base64,${Buffer.from(source).toString('base64')}`);
const raw={id:1,nome:'50m Crawl',categoria:'prova',modalidade_id:2,modalidade_nome:'Natação',ativo:'1',metricas:[{id:11,nome:'Tempo',codigo:'tempo_ms',tipo_valor:'tempo_ms',unidade:'ms',direcao:'menor_melhor',ordem_comparacao:1,obrigatoria:1},{id:12,nome:'Braçadas',codigo:'bracadas',tipo_valor:'inteiro',unidade:'reps',direcao:'menor_melhor',ordem_comparacao:2,obrigatoria:0}]};
const def=officialDefinition(raw);
const record=(id,student,value,date='2026-10-20')=>officialRecord({id,definicao_id:1,aluno_id:student,aluno_nome:student?`Aluno ${student}`:null,data_recorde:date,origem:'academia',valores:value==null?[]:[{metrica_id:11,valor_tempo_ms:value},{metrica_id:12,valor_int:20}]},[def]);
test('adapta definições com todas as métricas e normaliza status e categoria',()=>{
 assert.equal(def.metric.type,'tempo');assert.equal(def.metric.better,'menor');assert.equal(def.metric.api.id,11);
 assert.equal(def.extraMetrics.length,1);assert.equal(def.metrics.length,2);
 assert.equal(officialDefinition({...raw,ativo:'0',categoria:'teste_fisico'}).active,false);
 assert.equal(officialDefinition({...raw,categoria:'teste_fisico'}).category,'teste');
});
test('mantém os valores de todas as métricas e permite marca sem aluno',()=>{
 const r=record(1,null,35421);assert.equal(r.student,null);assert.equal(r.value,35421);assert.equal(r.values[1].valor_int,20);
 assert.equal(r.api.aluno_id,null);assert.equal(r.date,'2026-10-20');
});
test('ranking mantém a melhor marca de cada aluno, inclui academia e ordena pela direção',()=>{
 const records=[record(1,1,40000),record(2,1,35000),record(3,2,38000),record(4,null,34000)];
 const rank=rankRecords(records,def);assert.deepEqual(rank.map(r=>r.id),[4,2,3]);
 const reverse=rankRecords(records,{...def,metric:{...def.metric,better:'maior'}});assert.deepEqual(reverse.map(r=>r.id),[1,3,4]);
});
test('não inventa uma melhor marca quando falta a métrica principal',()=>{
 const records=[record(1,1,null),record(2,2,36000)];assert.equal(records[0].value,null);
 assert.deepEqual(rankRecords(records,def).map(r=>r.id),[2]);
 const stats=definitionStats([def],records)[0];assert.equal(stats.count,2);assert.equal(stats.top.value,36000);
 assert.equal(definitionStats([def],records,false)[0].count,null);
});
test('desempata por data mais antiga sem alterar o array de origem',()=>{
 const records=[record(1,1,35000,'2026-10-20'),record(2,2,35000,'2026-10-19')];assert.deepEqual(rankRecords(records,def).map(r=>r.id),[2,1]);assert.equal(records[0].id,1);
});
test('interpreta tempos com milissegundos e rejeita intervalos inválidos',()=>{
 assert.equal(parseTime('1:05,321'),65321);assert.equal(parseTime('35.421'),35421);assert.equal(parseTime('1:00:00'),3600000);
 for(const invalid of ['1:60','0:99','1abc','1:2:3:4','1.2.3'])assert.ok(Number.isNaN(parseTime(invalid)));
 assert.equal(parseTime(timeInput(35421)),35421);assert.equal(parseTime(timeInput(3599999)),3599999);
});
test('não confunde decimal com milhar nem aceita fracionários em inteiros',()=>{
 const decimal={tipo_valor:'decimal'},integer={tipo_valor:'inteiro'};
 assert.equal(parseMark('112.5',decimal),112.5);assert.equal(parseMark('112,5',decimal),112.5);
 assert.ok(Number.isNaN(parseMark('1,2,3',decimal)));assert.ok(Number.isNaN(parseMark('1.5',integer)));
 assert.equal(parseMark('',integer),null);assert.equal(parseMark('10',integer),10);
});
test('apresenta unidades e tempos no formato oficial',()=>{
 assert.equal(fmtMetric(140,{type:'carga'}),'140 kg');assert.equal(fmtMetric(null,{type:'tempo'}),'—');
 assert.equal(fmtMetric(35400,{type:'tempo'}),'35,40 s');
});
