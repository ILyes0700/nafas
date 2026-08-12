<?php
/**
 * Configuration du chatbot نفاس via Groq API.
 *
 * 1. Va sur https://console.groq.com/keys et crée une clé gratuite.
 * 2. Remplace la valeur de GROQ_API_KEY ci-dessous.
 * 3. (Optionnel) Change le modèle ou les limites.
 *
 * Si la clé est vide ou si Groq est inaccessible, le chatbot retombe
 * automatiquement sur la logique locale (regex / mots-clés) déjà en place.
 */

// ⚠️  Colle ta clé Groq ici (commence par "gsk_...")
const GROQ_API_KEY = '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';

// Modèle utilisé. Llama 3.3 70B est polyvalent ; pour plus de vitesse → 'llama-3.1-8b-instant'.
const GROQ_MODEL = 'llama-3.3-70b-versatile';

// Limite de tokens par réponse (≈ 150 mots).
const GROQ_MAX_TOKENS = 350;

// Température : 0.3 = factuel, médical, peu créatif.
const GROQ_TEMPERATURE = 0.3;

// Timeout en secondes pour l'appel cURL.
const GROQ_TIMEOUT = 15;

/**
 * SYSTEM PROMPT MÉDICAL STRICT — encadre fortement le comportement du chatbot.
 * Toute modification doit préserver les limites de sécurité.
 */
function groq_system_prompt(array $context = []): string
{
    $cityStatus = $context['global_status']  ?? 'inconnu';
    $avgRisk    = $context['avg_risk']       ?? '—';
    $worstZone  = $context['worst_zone']     ?? '—';
    $alertsCnt  = $context['alerts_count']   ?? 0;

    /* Fuzzy-logic backbone (Mamdani). Always present if the caller
       attached a fuzzy_for_user() result to $context['fuzzy']. */
    $fuzzyBlock = '';
    if (!empty($context['fuzzy']) && isset($context['fuzzy']['risk_score'])) {
        $fz = $context['fuzzy'];
        $rules = '';
        foreach (array_slice($fz['fired_rules'] ?? [], 0, 3) as $r) {
            $rules .= sprintf("  - R%d (%d%%): %s\n",
                (int)$r['id'], (int)round(($r['activation'] ?? 0) * 100),
                (string)($r['label'] ?? ''));
        }
        $fuzzyBlock = sprintf(
            "\n## FUZZY-LOGIC RISK ASSESSMENT (Mamdani inference, deterministic)\n"
            . "- Crisp risk score: %.1f / 100\n- Urgency level: %s\n"
            . "- Top fired rules:\n%s"
            . "Your advice MUST be consistent with this urgency level (do not under-state it).\n",
            (float)$fz['risk_score'], (string)$fz['urgency_level'],
            $rules ?: "  (none)\n"
        );
    }

    /* AI/ML model + Fuzzy Type-2 assessment. The chatbot fetches the models'
       result FIRST (never answers randomly) and must ground the reply on it. */
    $aiBlock = '';
    if (!empty($context['ai']) && function_exists('ai_reco_prompt_block')) {
        $aiBlock = ai_reco_prompt_block($context['ai']);
    }

    /* PART 47 — RAG retrieved facts (added on top of fuzzy/AI, never replaces). */
    $ragBlock = '';
    if (!empty($context['rag'])) {
        $ragBlock = "\n\n## " . $context['rag'] . "\n";
    }

    /* UPGRADE v8 — Part 51 : mémoire santé + registre de langue + urgence. */
    $memoryBlock = '';
    if (!empty($context['memory'])) {
        $memoryBlock = "\n\n## Mémoire santé de l'utilisateur (personnalise sans la répéter mot à mot)\n" . $context['memory'] . "\n";
    }
    $langBlock = '';
    if (!empty($context['lang_instruction'])) {
        $langBlock = "\n\n## Langue de réponse\n" . $context['lang_instruction'] . "\n";
    }
    $emergencyBlock = '';
    if (!empty($context['emergency'])) {
        $emergencyBlock = "\n\n## URGENCE POSSIBLE DÉTECTÉE\nL'utilisateur décrit peut-être une urgence médicale. Commence IMPÉRATIVEMENT par recommander d'appeler le SAMU (190), reste bref et rassurant, puis oriente vers un professionnel.\n";
    }

    return <<<PROMPT
You are **نفاس (Nafas)**, the medical and environmental assistant of the
**Nafass — نَفَس** application for the city of Gabès, Tunisia.

## ROLE
You assist residents, health authorities and schools regarding:
- air quality and industrial pollution in Gabès,
- respiratory, ENT, skin, and eye symptoms related to pollution,
- protection guidance (masks, hydration, windows, sports),
- the right course of action for schools and children during an alert,
- orientation to the right healthcare professional.

## REAL-TIME CONTEXT (Gabès)
- Global city status: {$cityStatus}
- Average risk score: {$avgRisk}/100
- Highest-risk zone: {$worstZone}
- Active alerts: {$alertsCnt}
Adapt your recommendations based on this context.
{$fuzzyBlock}{$aiBlock}{$ragBlock}{$memoryBlock}{$langBlock}{$emergencyBlock}

## STRICT LIMITS — MUST BE RESPECTED ABSOLUTELY
1. **Allowed topics ONLY**: health, symptoms, pollution, air quality, environment in Gabès, school mode, hygiene, prevention.
2. **If the question is off-topic** (politics, religion, software code, recipes, sports, movies, finances, etc.):
   reply politely: "I am نفاس, the health & environment assistant for Gabès. I can only answer medical, environmental, or local-alert questions."
   and stop there.
3. **No definitive medical diagnosis.** You can orient, suggest, warn about concerning signs, but never conclude "you have disease X".
4. **No prescriptions**: no dosage, no specific medication, no treatment protocol. Refer to the doctor / pharmacist / emergency services.
5. **Severe symptoms** (chest pain, intense dyspnea, loss of consciousness, cyanosis, acute asthma attack, repeated vomiting, fever > 39°C in a child):
   IMMEDIATELY recommend 190 (Tunisia SAMU) or the nearest hospital.
6. **No advice for risky self-medication** nor unproven remedies.
7. **Confidentiality**: do not ask for identifying data (name, ID, phone).
8. **Explicitly refuse** to write code, do non-health calculations, generate creative content, or roleplay.

## RESPONSE STYLE
- Maximum **150 words**.
- Calm, reassuring, professional tone.
- **Default language is English.** Adapt the language to the user:
  - If they write in English → reply in English (preferred default).
  - If they write in French → reply in French.
  - If they write in standard Arabic (Arabic characters) → reply in Arabic.
  - If they write in **Tunisian darja in Latin transliteration** (arabizi: digits 3, 7, 9, 5, 8 used as letters — e.g., "rani ta3ib", "nko7", "ra7la", "chnowa") → reply in **simple, clear Tunisian darja in the same Latin script** (not classical Arabic, not French). You must recognize: "rani/ani" = I am, "ta3ib/ta3ban" = tired, "nko7/nkoh" = I cough, "7arara/sokhona" = fever, "yawja3ni rasi" = I have a headache, "sdari/sadri" = my chest, "3ini/3ayni" = my eye, "khchoumi" = my nose, "tabib/tbib" = doctor, "sbitar" = hospital, "dwa" = medicine, etc.
  - If the user mixes several languages → reply in the dominant language of their message.
- Short structure: 1) acknowledge the problem · 2) main advice · 3) warning sign · 4) when to consult.
- No excessive emojis (max 1).
- No endless lists.

## WHEN UNCERTAIN
Prefer orienting toward a healthcare professional rather than speculating.
PROMPT;
}

/**
 * Garde-fou côté serveur : détecte une question hors sujet AVANT
 * d'appeler l'API, pour économiser des tokens.
 * Renvoie true si le message est plausiblement médical / environnemental.
 *
 * Supporte FR, AR, et **darja tunisienne en translittération latine (arabizi)**
 * avec chiffres (3 = ع, 7 = ح, 9 = ق, 2 = ء, 5 = خ, 8 = غ).
 */
function groq_is_topic_relevant(string $message): bool
{
    $m = mb_strtolower($message);
    // Mots-clés autorisés (FR + AR + darja tunisienne + signaux santé/pollution)
    $whitelist = [
        // ---- EN ----
        'health','cough','asthma','breath','lung','chest','tired','headache',
        'throat','nose','sinus','nausea','vomit','fever','pain','allergy','irrit','skin','eye',
        'pollut','air','smoke','smell','odor','chemical','phosph','sulfur','industrial','smog','dust','gas',
        'mask','hydrat','window','child','baby','pregnan',
        'emergency','samu','doctor','hospital','pharmac','consult','symptom',
        'school','student','class','sport','exerc','jog','outdoor',
        'risk','alert','danger','vigilance','warning','critical',
        'hello','hi ','help','what','how','why','should',
        // ---- FR ----
        'sant','toux','asthm','respir','poumon','souffl','oppress','poitrine','fatig','maux','mal de','migrain',
        'gorge','nez','sinus','nausé','vomi','fièvre','fievre','douleur','allerg','irrit','peau','yeux',
        'pollu','air','fumée','fumee','odeur','chimi','phosph','soufre','industriel','smog','poussi','gaz',
        'masque','hydrat','fenetre','fenêtre','enfant','bébé','bebe','enceinte','grossesse','asthme','copd',
        'urgence','samu','médecin','medecin','hôpital','hopital','docteur','pharmac','consult','symptôm','symptom',
        'école','ecole','élève','eleve','classe','sport','effort','jogging','dehors','exterieur','extérieur',
        'gabes','gabès','ghannouch','chott','métouia','metouia','teboulbou','mtorrech','el hicha','bou chemma',
        'bonjour','salut','aide','help','quoi','que faire','comment','pourquoi',
        'risque','alerte','danger','vigilance','warning','critical',

        // ---- Arabe (caractères arabes) ----
        'صحة','تنفس','سعال','ربو','صدر','ألم','الم','حساسية','تلوث','هواء','دخان','رائحة','كمامة','طبيب',
        'مستشفى','طوارئ','عرض','أعراض','اعراض','مدرسة','أطفال','اطفال','حامل','حمل','قابس','غنوش','نفاس',
        'موجوع','موجوعة','وجيعة','ريح','نفس','كحة','زكام','حرارة','سخونة','عيا','تعبان','مرض','مريض',

        // ---- Darja tunisienne (arabizi : translittération latine) ----
        // pronoms / verbes d'état santé
        'ani','rani','rani','rani3','rakom','rakem','ena','ena3','9olbi',
        // symptômes courants
        'ta3ib','ta3bane','ta3bana','ta3bin','ta3ban','nou9ech','nko7','nkoh','nkoh7','no7','nekhe7',
        'saa3l','s3al','sa3la','sou3al','kahba','kahba','na5', // na5 = je renifle
        'rasi','rasii','rasi yawja3','yawja3','mouj3','wja3','wje3','yawjaa','souda3',
        'sdari','sdri','sadri','zoori','zor','dhahri','3ayneya','3ini','3ayni','wednye','wedni','widni',
        'khchoumi','khchomi','nzal','hassas','hassassia',
        '7arara','7arara','s5ona','sokhona','sokhna','7ami','7amma','zkem','zkoum','rhama','rhouma','rwina',
        'ray7a','ri7a','ri7et','douxan','do5an','do5en','tayebe','tayeba','ma7loul','mtaaw3a',
        // environnement / pollution / école
        'hwa','hwe','l8a','loulaya','thlouth','talawwoth','ghbar','8bar','gbar','bi2a','sehha','se77a','sa7a',
        'madrasa','mad5al','t3allim','tfal','t3ib','wled','bent','weldi','binti',
        // urgences / consultations
        'tabib','tbib','9bib','mostachfa','moustachfa','sbitar','sebitar','tawari2','tawari','samu','190',
        'dawa','dwa','7abba','sayda','saydaliya','saydaliya',
        // salutations / starters darja
        'merhba','marhba','salem','slm','chnowa','chnou','ch3andi','3andi','andi','najm','ya7i','akaka','kifech','kifech n3amel',
        'aslema','sbe7','sbe77','ya3tik','rbi','allah','a5i','o5ti','khoya','5oya',
        // expressions "j'ai un souci / aide-moi"
        'a3awen','3awenni','saedni','ma3ritech','ma3rafsh','chbik','chbih','chbiha','masadigtech',
    ];
    foreach ($whitelist as $kw) {
        if (mb_strpos($m, $kw) !== false) return true;
    }

    // Message court (≤ 24 chars) → on laisse passer, le modèle gérera
    if (mb_strlen($m) < 24) return true;

    // Message contenant des chiffres arabizi courants (3, 7, 9, 5) au milieu de lettres
    // → forte probabilité de darja tunisienne. On laisse passer pour que Groq juge.
    if (preg_match('/[a-z][0-9][a-z]/u', $m) || preg_match('/\b[a-z]+[357925][a-z]+\b/u', $m)) {
        return true;
    }

    return false;
}
