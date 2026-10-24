<?php
require_once __DIR__ . '/../config/ai.php';

if (!defined('AI_PROMPT_VERSION')) define('AI_PROMPT_VERSION', 'v2');

function ai_extract_notification(string $sourceText): array {
    $sourceText = trim($sourceText);
    if ($sourceText === '') throw new RuntimeException('Notification text is empty.');
    if (OPENAI_API_KEY === '') throw new RuntimeException('OPENAI_API_KEY is not configured on the server.');

    $schema = [
        'type'=>'object',
        'additionalProperties'=>false,
        'properties'=>[
            'organization'=>['type'=>['string','null']],
            'department'=>['type'=>['string','null']],
            'job_title'=>['type'=>['string','null']],
            'job_type'=>['type'=>['string','null']],
            'location'=>['type'=>['string','null']],
            'state'=>['type'=>['string','null']],
            'district'=>['type'=>['string','null']],
            'vacancy_count'=>['type'=>['integer','null']],
            'min_age'=>['type'=>['integer','null']],
            'max_age'=>['type'=>['integer','null']],
            'gender_requirement'=>['type'=>['string','null']],
            'nationality_requirement'=>['type'=>['string','null']],
            'education_required'=>['type'=>['string','null']],
            'experience_required'=>['type'=>['string','null']],
            'domicile_requirement'=>['type'=>['string','null']],
            'category_rules'=>['type'=>['string','null']],
            'pwd_rules'=>['type'=>['string','null']],
            'ex_serviceman_rules'=>['type'=>['string','null']],
            'age_relaxation_rules'=>['type'=>['string','null']],
            'allowed_categories'=>['type'=>['string','null']],
            'pwd_min_percentage'=>['type'=>['number','null']],
            'pwd_eligibility'=>['type'=>['string','null']],
            'domicile_states'=>['type'=>['string','null']],
            'qualification_rules'=>['type'=>['string','null']],
            'minimum_experience_months'=>['type'=>['integer','null']],
            'experience_notes'=>['type'=>['string','null']],
            'other_conditions'=>['type'=>['string','null']],
            'notification_date'=>['type'=>['string','null']],
            'application_start'=>['type'=>['string','null']],
            'application_end'=>['type'=>['string','null']],
            'fee_last_date'=>['type'=>['string','null']],
            'exam_date'=>['type'=>['string','null']],
            'admit_card_date'=>['type'=>['string','null']],
            'result_date'=>['type'=>['string','null']],
            'official_notification_url'=>['type'=>['string','null']],
            'official_application_url'=>['type'=>['string','null']],
            'confidence'=>['type'=>'number'],
            'warnings'=>['type'=>'array','items'=>['type'=>'string']]
        ],
        'required'=>[
            'organization','department','job_title','job_type','location','state','district',
            'vacancy_count','min_age','max_age','gender_requirement','nationality_requirement',
            'education_required','experience_required','domicile_requirement','category_rules',
            'pwd_rules','ex_serviceman_rules','age_relaxation_rules','allowed_categories','pwd_min_percentage','pwd_eligibility','domicile_states','qualification_rules','minimum_experience_months','experience_notes','other_conditions','notification_date','application_start',
            'application_end','fee_last_date','exam_date','admit_card_date','result_date',
            'official_notification_url','official_application_url','confidence','warnings'
        ]
    ];

    $payload = [
        'model'=>AI_MODEL,
        'input'=>[
            [
                'role'=>'system',
                'content'=>[
                    ['type'=>'input_text','text'=>
                        "You extract structured facts from official Indian government recruitment notifications. ".
                        "Use only information explicitly supported by the supplied text. Never invent or infer missing facts. ".
                        "If a value is ambiguous or absent, return null and add a warning. Dates must be ISO YYYY-MM-DD only when clearly stated; otherwise null. ".
                        "Do not decide legal eligibility. Extract rules faithfully for later human verification. Preserve important category, PwD, ex-serviceman, domicile and qualification conditions. When a relaxation/category rule is explicitly stated, summarize it faithfully as text; do not calculate eligibility. Extract a numeric PwD threshold or minimum experience months only when explicitly stated."
                    ]
                ]
            ],
            [
                'role'=>'user',
                'content'=>[
                    ['type'=>'input_text','text'=>"Extract the recruitment facts from this notification:\n\n".$sourceText]
                ]
            ]
        ],
        'text'=>[
            'format'=>[
                'type'=>'json_schema',
                'name'=>'government_job_notification',
                'strict'=>true,
                'schema'=>$schema
            ]
        ]
    ];

    $ch=curl_init('https://api.openai.com/v1/responses');
    curl_setopt_array($ch,[
        CURLOPT_POST=>true,
        CURLOPT_HTTPHEADER=>[
            'Authorization: Bearer '.OPENAI_API_KEY,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS=>json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_TIMEOUT=>120
    ]);
    $raw=curl_exec($ch);
    $err=curl_error($ch);
    $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);
    curl_close($ch);
    if($raw===false || $err) throw new RuntimeException('AI request failed: '.$err);
    $resp=json_decode($raw,true);
    if($code<200 || $code>=300) throw new RuntimeException('AI API error (HTTP '.$code.').');
    $text=$resp['output_text']??null;
    if(!$text && isset($resp['output'])) {
        foreach($resp['output'] as $item) foreach(($item['content']??[]) as $c) {
            if(isset($c['text'])) {$text=$c['text']; break 2;}
        }
    }
    if(!$text) throw new RuntimeException('AI returned no structured output.');
    $data=json_decode($text,true);
    if(!is_array($data)) throw new RuntimeException('AI returned invalid JSON.');
    return $data;
}

function extract_pdf_text_local(string $path): string {
    if(!is_file($path)) throw new RuntimeException('Notification file not found.');
    $bin = trim((string)@shell_exec('command -v pdftotext 2>/dev/null'));
    if($bin==='') throw new RuntimeException('PDF text extraction is not available on this server. Paste the notification text for AI review.');
    $tmp=tempnam(sys_get_temp_dir(),'govjob-');
    $cmd=escapeshellarg($bin).' -layout '.escapeshellarg($path).' '.escapeshellarg($tmp);
    @shell_exec($cmd.' 2>/dev/null');
    $text=is_file($tmp)?file_get_contents($tmp):'';
    @unlink($tmp);
    $text=trim((string)$text);
    if($text==='') throw new RuntimeException('Could not extract readable text from this PDF. Paste the notification text for AI review.');
    return $text;
}
