<?php

namespace Database\Seeders;

/**
 * Static workflow definitions keyed by case_type code(s).
 *
 * Case type codes come from CaseTypeSeeder (never modified here):
 *   TO=Tourist, ST=Student, WK=Work Permit, EM=EMIT, FV=Visitor Record, PS=PST
 *   EE=Express Entry, AR=ARRIMA, PQ=PEQ, PI=Pilot, SW=Skilled Worker,
 *   SP=Sponsorship, HU=Humanitario
 *   AS=Refugee/Asylum, AP=Appeal, FC=Federal Court, ER=ERAR
 *   CZ=Citizenship, CC=Citizenship Certificate, PA=Passport, TD=Travel Document
 *
 * Structure: [ 'case_type_codes' => [...], 'stages' => [ [...stage], ... ] ]
 * Each stage:  code, color, is_terminal, sort_order, name, description, templates[]
 * Each template: code, is_required, blocks_stage_completion, default_type,
 *                default_priority, due_offset_days, name, description
 */
class WorkflowSeedData
{
    /**
     * Returns all workflow definitions.
     * @return array<int, array{case_type_codes: string[], stages: array}>
     */
    public static function all(): array
    {
        return [
            self::temporaryResidence(),
            self::emitPst(),
            self::expressEntrySkilled(),
            self::sponsorship(),
            self::quebecRP(),
            self::federalPilots(),
            self::refugeeAppeals(),
            self::citizenship(),
            self::travelDocuments(),
        ];
    }

    // ── 1. Residencia Temporal (Tourist, Student, Work Permit, Visitor Record) ─────

    private static function temporaryResidence(): array
    {
        return [
            'case_type_codes' => ['TO', 'ST', 'WK', 'FV'],
            'stages' => [
                [
                    'code'        => 'INTAKE',
                    'color'       => 'info',
                    'is_terminal' => false,
                    'sort_order'  => 0,
                    'name'        => ['es' => 'Evaluación y Recepción', 'en' => 'Intake & Assessment', 'fr' => 'Évaluation et réception'],
                    'description' => ['es' => 'Revisión inicial de elegibilidad y recopilación de documentos básicos.', 'en' => 'Initial eligibility review and collection of basic documents.', 'fr' => 'Révision initiale d\'éligibilité et collecte des documents de base.'],
                    'templates'   => [
                        [
                            'code'                    => 'CONSULT_INICIAL',
                            'is_required'             => true,
                            'blocks_stage_completion' => true,
                            'default_type'            => 'other',
                            'default_priority'        => 'high',
                            'due_offset_days'         => 3,
                            'name'        => ['es' => 'Consulta inicial con el cliente', 'en' => 'Initial client consultation', 'fr' => 'Consultation initiale avec le client'],
                            'description' => ['es' => 'Revisar historial de viajes, estatus migratorio y objetivo de la solicitud.', 'en' => 'Review travel history, immigration status, and application objective.', 'fr' => 'Réviser l\'historique de voyages, le statut migratoire et l\'objectif de la demande.'],
                        ],
                        [
                            'code'                    => 'DOCS_IDENTIDAD',
                            'is_required'             => true,
                            'blocks_stage_completion' => true,
                            'default_type'            => 'document',
                            'default_priority'        => 'high',
                            'due_offset_days'         => 5,
                            'name'        => ['es' => 'Recopilación de documentos de identidad', 'en' => 'Identity documents collection', 'fr' => 'Collecte des documents d\'identité'],
                            'description' => ['es' => 'Pasaporte vigente, estado civil, actas de nacimiento.', 'en' => 'Valid passport, marital status, birth certificates.', 'fr' => 'Passeport valide, état civil, actes de naissance.'],
                        ],
                        [
                            'code'                    => 'VERIFICACION_ELEGIBILIDAD',
                            'is_required'             => true,
                            'blocks_stage_completion' => true,
                            'default_type'            => 'other',
                            'default_priority'        => 'medium',
                            'due_offset_days'         => 7,
                            'name'        => ['es' => 'Verificación de elegibilidad', 'en' => 'Eligibility verification', 'fr' => 'Vérification d\'éligibilité'],
                            'description' => ['es' => 'Confirmar que el cliente cumple los requisitos mínimos para el tipo de visa.', 'en' => 'Confirm the client meets the minimum requirements for the visa type.', 'fr' => 'Confirmer que le client répond aux exigences minimales pour le type de visa.'],
                        ],
                    ],
                ],
                [
                    'code'        => 'PREPARACION',
                    'color'       => 'warning',
                    'is_terminal' => false,
                    'sort_order'  => 1,
                    'name'        => ['es' => 'Preparación de Solicitud', 'en' => 'Application Preparation', 'fr' => 'Préparation de la demande'],
                    'description' => ['es' => 'Llenado de formularios, traducciones y revisión final antes del envío.', 'en' => 'Form completion, translations, and final review before submission.', 'fr' => 'Remplissage des formulaires, traductions et révision finale avant soumission.'],
                    'templates'   => [
                        [
                            'code'                    => 'FORMULARIOS',
                            'is_required'             => true,
                            'blocks_stage_completion' => true,
                            'default_type'            => 'case_creation',
                            'default_priority'        => 'high',
                            'due_offset_days'         => 14,
                            'name'        => ['es' => 'Llenado de formularios oficiales', 'en' => 'Complete official forms', 'fr' => 'Remplissage des formulaires officiels'],
                            'description' => ['es' => 'Completar y revisar los formularios IMM requeridos.', 'en' => 'Complete and review the required IMM forms.', 'fr' => 'Compléter et réviser les formulaires IMM requis.'],
                        ],
                        [
                            'code'                    => 'TRADUCCION_DOCS',
                            'is_required'             => false,
                            'blocks_stage_completion' => false,
                            'default_type'            => 'translation',
                            'default_priority'        => 'medium',
                            'due_offset_days'         => 14,
                            'name'        => ['es' => 'Traducción de documentos', 'en' => 'Document translation', 'fr' => 'Traduction de documents'],
                            'description' => ['es' => 'Traducción certificada de documentos en idioma extranjero.', 'en' => 'Certified translation of foreign-language documents.', 'fr' => 'Traduction certifiée de documents en langue étrangère.'],
                        ],
                        [
                            'code'                    => 'PAGO_TARIFAS',
                            'is_required'             => true,
                            'blocks_stage_completion' => true,
                            'default_type'            => 'accounting',
                            'default_priority'        => 'high',
                            'due_offset_days'         => 16,
                            'name'        => ['es' => 'Pago de tarifas gubernamentales', 'en' => 'Government fee payment', 'fr' => 'Paiement des frais gouvernementaux'],
                            'description' => ['es' => 'Realizar el pago de las tarifas de procesamiento en IRCC.', 'en' => 'Process IRCC application fees.', 'fr' => 'Effectuer le paiement des frais de traitement à IRCC.'],
                        ],
                        [
                            'code'                    => 'ENVIO_SOLICITUD',
                            'is_required'             => true,
                            'blocks_stage_completion' => true,
                            'default_type'            => 'filing',
                            'default_priority'        => 'urgent',
                            'due_offset_days'         => 18,
                            'name'        => ['es' => 'Envío de la solicitud', 'en' => 'Submit application', 'fr' => 'Soumission de la demande'],
                            'description' => ['es' => 'Enviar la solicitud a través del portal de IRCC o por correo.', 'en' => 'Submit the application via IRCC portal or by mail.', 'fr' => 'Soumettre la demande via le portail IRCC ou par courrier.'],
                        ],
                    ],
                ],
                [
                    'code'        => 'SEGUIMIENTO',
                    'color'       => 'success',
                    'is_terminal' => true,
                    'sort_order'  => 2,
                    'name'        => ['es' => 'Seguimiento y Resolución', 'en' => 'Follow-up & Resolution', 'fr' => 'Suivi et résolution'],
                    'description' => ['es' => 'Gestión de biométricos, exámenes médicos y recepción de la decisión.', 'en' => 'Biometrics, medical exams, and final decision management.', 'fr' => 'Gestion des données biométriques, examens médicaux et décision finale.'],
                    'templates'   => [
                        [
                            'code'                    => 'BIOMETRICOS',
                            'is_required'             => false,
                            'blocks_stage_completion' => false,
                            'default_type'            => 'other',
                            'default_priority'        => 'high',
                            'due_offset_days'         => 30,
                            'name'        => ['es' => 'Gestión de biométricos', 'en' => 'Biometrics management', 'fr' => 'Gestion des données biométriques'],
                            'description' => ['es' => 'Acompañar al cliente al centro VAC para toma de biométricos.', 'en' => 'Accompany client to VAC center for biometric collection.', 'fr' => 'Accompagner le client au centre VAC pour la prise des données biométriques.'],
                        ],
                        [
                            'code'                    => 'DECISION_FINAL',
                            'is_required'             => true,
                            'blocks_stage_completion' => true,
                            'default_type'            => 'other',
                            'default_priority'        => 'high',
                            'due_offset_days'         => null,
                            'name'        => ['es' => 'Recepción y notificación de decisión', 'en' => 'Decision received and notified', 'fr' => 'Décision reçue et notifiée'],
                            'description' => ['es' => 'Comunicar al cliente el resultado y archivar el expediente.', 'en' => 'Communicate outcome to client and archive the case.', 'fr' => 'Communiquer le résultat au client et archiver le dossier.'],
                        ],
                    ],
                ],
            ],
        ];
    }

    // ── 2. EMIT / PST (EM, PS) ────────────────────────────────────────────────────

    private static function emitPst(): array
    {
        return [
            'case_type_codes' => ['EM', 'PS'],
            'stages' => [
                [
                    'code'        => 'ELEGIBILIDAD',
                    'color'       => 'info',
                    'is_terminal' => false,
                    'sort_order'  => 0,
                    'name'        => ['es' => 'Evaluación de Elegibilidad', 'en' => 'Eligibility Assessment', 'fr' => 'Évaluation d\'éligibilité'],
                    'description' => ['es' => 'Revisión de condiciones y requisitos específicos del permiso.', 'en' => 'Review of specific permit conditions and requirements.', 'fr' => 'Révision des conditions et exigences spécifiques du permis.'],
                    'templates'   => [
                        [
                            'code'                    => 'CONDICIONES_EMPLEADOR',
                            'is_required'             => true,
                            'blocks_stage_completion' => true,
                            'default_type'            => 'other',
                            'default_priority'        => 'high',
                            'due_offset_days'         => 5,
                            'name'        => ['es' => 'Verificación de condiciones del empleador', 'en' => 'Employer conditions verification', 'fr' => 'Vérification des conditions de l\'employeur'],
                            'description' => ['es' => 'Revisar LMIA, oferta de trabajo y cumplimiento regulatorio.', 'en' => 'Review LMIA, job offer, and regulatory compliance.', 'fr' => 'Réviser le EIMT, l\'offre d\'emploi et la conformité réglementaire.'],
                        ],
                        [
                            'code'                    => 'DOCS_SOPORTE',
                            'is_required'             => true,
                            'blocks_stage_completion' => true,
                            'default_type'            => 'document',
                            'default_priority'        => 'high',
                            'due_offset_days'         => 7,
                            'name'        => ['es' => 'Documentos de soporte', 'en' => 'Supporting documents', 'fr' => 'Documents justificatifs'],
                            'description' => ['es' => 'Pasaporte, contrato laboral, títulos y credenciales.', 'en' => 'Passport, employment contract, credentials and titles.', 'fr' => 'Passeport, contrat de travail, titres et références.'],
                        ],
                    ],
                ],
                [
                    'code'        => 'DOCUMENTACION',
                    'color'       => 'warning',
                    'is_terminal' => false,
                    'sort_order'  => 1,
                    'name'        => ['es' => 'Documentación y Envío', 'en' => 'Documentation & Submission', 'fr' => 'Documentation et soumission'],
                    'description' => ['es' => 'Preparación final del expediente y envío oficial.', 'en' => 'Final case preparation and official submission.', 'fr' => 'Préparation finale du dossier et soumission officielle.'],
                    'templates'   => [
                        [
                            'code'                    => 'FORMULARIO_EMIT',
                            'is_required'             => true,
                            'blocks_stage_completion' => true,
                            'default_type'            => 'case_creation',
                            'default_priority'        => 'urgent',
                            'due_offset_days'         => 12,
                            'name'        => ['es' => 'Completar formulario de solicitud', 'en' => 'Complete application form', 'fr' => 'Remplir le formulaire de demande'],
                            'description' => ['es' => 'IMM 5710 u otro formulario correspondiente al permiso solicitado.', 'en' => 'IMM 5710 or other form corresponding to the requested permit.', 'fr' => 'IMM 5710 ou autre formulaire correspondant au permis demandé.'],
                        ],
                        [
                            'code'                    => 'ENVIO_EMIT',
                            'is_required'             => true,
                            'blocks_stage_completion' => true,
                            'default_type'            => 'filing',
                            'default_priority'        => 'urgent',
                            'due_offset_days'         => 14,
                            'name'        => ['es' => 'Envío al IRCC', 'en' => 'Submit to IRCC', 'fr' => 'Soumission à IRCC'],
                            'description' => ['es' => 'Enviar paquete completo con acuse de recibo.', 'en' => 'Submit complete package with confirmation of receipt.', 'fr' => 'Soumettre le dossier complet avec accusé de réception.'],
                        ],
                    ],
                ],
                [
                    'code'        => 'RESOLUCION',
                    'color'       => 'success',
                    'is_terminal' => true,
                    'sort_order'  => 2,
                    'name'        => ['es' => 'Resolución', 'en' => 'Resolution', 'fr' => 'Résolution'],
                    'description' => ['es' => 'Seguimiento y recepción del permiso aprobado.', 'en' => 'Follow-up and receipt of approved permit.', 'fr' => 'Suivi et réception du permis approuvé.'],
                    'templates'   => [
                        [
                            'code'                    => 'RECEPCION_PERMISO',
                            'is_required'             => true,
                            'blocks_stage_completion' => true,
                            'default_type'            => 'other',
                            'default_priority'        => 'high',
                            'due_offset_days'         => null,
                            'name'        => ['es' => 'Recepción del permiso', 'en' => 'Permit received', 'fr' => 'Réception du permis'],
                            'description' => ['es' => 'Verificar el permiso recibido y notificar al cliente.', 'en' => 'Verify received permit and notify client.', 'fr' => 'Vérifier le permis reçu et notifier le client.'],
                        ],
                    ],
                ],
            ],
        ];
    }

    // ── 3. Express Entry / Skilled Worker (EE, SW) ───────────────────────────────

    private static function expressEntrySkilled(): array
    {
        return [
            'case_type_codes' => ['EE', 'SW'],
            'stages' => [
                [
                    'code'        => 'PRE_ITA',
                    'color'       => 'info',
                    'is_terminal' => false,
                    'sort_order'  => 0,
                    'name'        => ['es' => 'Pre-ITA: Perfil y Calificaciones', 'en' => 'Pre-ITA: Profile & Qualifications', 'fr' => 'Pré-ITA: Profil et qualifications'],
                    'description' => ['es' => 'Preparación del perfil, evaluación de credenciales y exámenes de idiomas.', 'en' => 'Profile preparation, credential assessment, and language exams.', 'fr' => 'Préparation du profil, évaluation des diplômes et examens de langue.'],
                    'templates'   => [
                        [
                            'code'                    => 'ECA',
                            'is_required'             => true,
                            'blocks_stage_completion' => true,
                            'default_type'            => 'document',
                            'default_priority'        => 'high',
                            'due_offset_days'         => 30,
                            'name'        => ['es' => 'Evaluación de Credenciales Educativas (ECA)', 'en' => 'Educational Credential Assessment (ECA)', 'fr' => 'Évaluation des diplômes (ECA)'],
                            'description' => ['es' => 'Gestionar la evaluación con WES u organismo reconocido.', 'en' => 'Manage assessment through WES or recognized body.', 'fr' => 'Gérer l\'évaluation via WES ou un organisme reconnu.'],
                        ],
                        [
                            'code'                    => 'EXAMEN_IDIOMAS',
                            'is_required'             => true,
                            'blocks_stage_completion' => true,
                            'default_type'            => 'other',
                            'default_priority'        => 'high',
                            'due_offset_days'         => 45,
                            'name'        => ['es' => 'Examen de idiomas (IELTS/TEF/CELPIP)', 'en' => 'Language test (IELTS/TEF/CELPIP)', 'fr' => 'Test de langue (IELTS/TEF/CELPIP)'],
                            'description' => ['es' => 'Inscripción y seguimiento del examen oficial de idiomas.', 'en' => 'Registration and follow-up for the official language exam.', 'fr' => 'Inscription et suivi de l\'examen officiel de langue.'],
                        ],
                        [
                            'code'                    => 'PERFIL_EE',
                            'is_required'             => true,
                            'blocks_stage_completion' => true,
                            'default_type'            => 'case_creation',
                            'default_priority'        => 'medium',
                            'due_offset_days'         => 60,
                            'name'        => ['es' => 'Creación/actualización de perfil en Express Entry', 'en' => 'Express Entry profile creation/update', 'fr' => 'Création/mise à jour du profil Express Entrée'],
                            'description' => ['es' => 'Crear o actualizar el perfil en el sistema de Express Entry de IRCC.', 'en' => 'Create or update the profile in the IRCC Express Entry pool.', 'fr' => 'Créer ou mettre à jour le profil dans le bassin Express Entrée d\'IRCC.'],
                        ],
                    ],
                ],
                [
                    'code'        => 'POST_ITA',
                    'color'       => 'warning',
                    'is_terminal' => false,
                    'sort_order'  => 1,
                    'name'        => ['es' => 'Post-ITA: Documentación', 'en' => 'Post-ITA: Documentation', 'fr' => 'Post-ITA: Documentation'],
                    'description' => ['es' => 'Recopilación de documentos requeridos tras la Invitación a Solicitar.', 'en' => 'Collection of required documents after Invitation to Apply.', 'fr' => 'Collecte des documents requis après l\'invitation à présenter une demande.'],
                    'templates'   => [
                        [
                            'code'                    => 'CERTIFICADOS_POLICIA',
                            'is_required'             => true,
                            'blocks_stage_completion' => true,
                            'default_type'            => 'document',
                            'default_priority'        => 'urgent',
                            'due_offset_days'         => 20,
                            'name'        => ['es' => 'Certificados de policía', 'en' => 'Police clearance certificates', 'fr' => 'Certificats de police'],
                            'description' => ['es' => 'Obtener certificados de países de residencia de 6+ meses.', 'en' => 'Obtain certificates from countries of residence for 6+ months.', 'fr' => 'Obtenir les certificats des pays de résidence de 6 mois ou plus.'],
                        ],
                        [
                            'code'                    => 'CARTAS_REFERENCIA',
                            'is_required'             => true,
                            'blocks_stage_completion' => true,
                            'default_type'            => 'document',
                            'default_priority'        => 'high',
                            'due_offset_days'         => 25,
                            'name'        => ['es' => 'Cartas de referencia laboral', 'en' => 'Employment reference letters', 'fr' => 'Lettres de référence professionnelle'],
                            'description' => ['es' => 'Cartas de empleadores describiendo cargo, salario y funciones (TEER/NOC).', 'en' => 'Letters from employers describing position, salary and duties (TEER/NOC).', 'fr' => 'Lettres des employeurs décrivant le poste, le salaire et les fonctions (TEER/NOC).'],
                        ],
                        [
                            'code'                    => 'EXAMEN_MEDICO',
                            'is_required'             => true,
                            'blocks_stage_completion' => true,
                            'default_type'            => 'other',
                            'default_priority'        => 'urgent',
                            'due_offset_days'         => 30,
                            'name'        => ['es' => 'Examen médico (Panel Physician)', 'en' => 'Medical exam (Panel Physician)', 'fr' => 'Examen médical (médecin désigné)'],
                            'description' => ['es' => 'Coordinar examen médico con médico autorizado por IRCC.', 'en' => 'Coordinate medical exam with IRCC-authorized panel physician.', 'fr' => 'Coordonner l\'examen médical avec un médecin désigné par IRCC.'],
                        ],
                        [
                            'code'                    => 'ENVIO_PR',
                            'is_required'             => true,
                            'blocks_stage_completion' => true,
                            'default_type'            => 'filing',
                            'default_priority'        => 'urgent',
                            'due_offset_days'         => 55,
                            'name'        => ['es' => 'Envío del paquete de PR al IRCC', 'en' => 'Submit PR package to IRCC', 'fr' => 'Soumission du dossier de RP à IRCC'],
                            'description' => ['es' => 'Enviar solicitud completa dentro del plazo de 60 días desde la ITA.', 'en' => 'Submit full application within the 60-day ITA deadline.', 'fr' => 'Soumettre la demande complète dans le délai de 60 jours depuis l\'ITA.'],
                        ],
                    ],
                ],
                [
                    'code'        => 'ECPR',
                    'color'       => 'success',
                    'is_terminal' => true,
                    'sort_order'  => 2,
                    'name'        => ['es' => 'eCOPR y Residencia Permanente', 'en' => 'eCOPR & Permanent Residence', 'fr' => 'eCOPR et Résidence permanente'],
                    'description' => ['es' => 'Recepción del eCOPR y confirmación de estatus de residente permanente.', 'en' => 'Receipt of eCOPR and confirmation of permanent resident status.', 'fr' => 'Réception de l\'eCOPR et confirmation du statut de résident permanent.'],
                    'templates'   => [
                        [
                            'code'                    => 'BIOMETRICOS_PR',
                            'is_required'             => false,
                            'blocks_stage_completion' => false,
                            'default_type'            => 'other',
                            'default_priority'        => 'high',
                            'due_offset_days'         => null,
                            'name'        => ['es' => 'Biométricos (si aplica)', 'en' => 'Biometrics (if applicable)', 'fr' => 'Données biométriques (si applicable)'],
                            'description' => ['es' => 'Acompañar al cliente para toma de biométricos en centro VAC.', 'en' => 'Accompany client for biometric collection at VAC center.', 'fr' => 'Accompagner le client pour la prise des données biométriques au centre VAC.'],
                        ],
                        [
                            'code'                    => 'ECPR_RECIBIDO',
                            'is_required'             => true,
                            'blocks_stage_completion' => true,
                            'default_type'            => 'other',
                            'default_priority'        => 'high',
                            'due_offset_days'         => null,
                            'name'        => ['es' => 'Recepción del eCOPR', 'en' => 'eCOPR received', 'fr' => 'Réception de l\'eCOPR'],
                            'description' => ['es' => 'Confirmar recepción, notificar al cliente y registrar fecha de landing.', 'en' => 'Confirm receipt, notify client, and record landing date.', 'fr' => 'Confirmer la réception, notifier le client et enregistrer la date de débarquement.'],
                        ],
                    ],
                ],
            ],
        ];
    }

    // ── 4. Patrocinio Familiar (SP) ───────────────────────────────────────────────

    private static function sponsorship(): array
    {
        return [
            'case_type_codes' => ['SP'],
            'stages' => [
                [
                    'code'        => 'EVALUACION_PATROCINADOR',
                    'color'       => 'info',
                    'is_terminal' => false,
                    'sort_order'  => 0,
                    'name'        => ['es' => 'Evaluación del Patrocinador', 'en' => 'Sponsor Evaluation', 'fr' => 'Évaluation du parrain'],
                    'description' => ['es' => 'Verificación de antecedentes penales, financieros y elegibilidad del patrocinador.', 'en' => 'Background check, financial assessment, and sponsor eligibility.', 'fr' => 'Vérification des antécédents, évaluation financière et éligibilité du répondant.'],
                    'templates'   => [
                        [
                            'code'                    => 'ELEGIBILIDAD_PATROCINADOR',
                            'is_required'             => true,
                            'blocks_stage_completion' => true,
                            'default_type'            => 'other',
                            'default_priority'        => 'high',
                            'due_offset_days'         => 5,
                            'name'        => ['es' => 'Verificación de elegibilidad del patrocinador', 'en' => 'Sponsor eligibility check', 'fr' => 'Vérification de l\'éligibilité du répondant'],
                            'description' => ['es' => 'Confirmar estatus migratorio, no tener prohibiciones, y capacidad financiera.', 'en' => 'Confirm immigration status, no prohibitions, and financial capacity.', 'fr' => 'Confirmer le statut migratoire, l\'absence de restrictions et la capacité financière.'],
                        ],
                        [
                            'code'                    => 'UNDERTAKING',
                            'is_required'             => true,
                            'blocks_stage_completion' => true,
                            'default_type'            => 'document',
                            'default_priority'        => 'high',
                            'due_offset_days'         => 10,
                            'name'        => ['es' => 'Compromiso de manutención (Undertaking)', 'en' => 'Undertaking agreement', 'fr' => 'Engagement de parrainage (Undertaking)'],
                            'description' => ['es' => 'Calcular duración y firmar el compromiso legal de manutención.', 'en' => 'Calculate duration and sign the legal maintenance commitment.', 'fr' => 'Calculer la durée et signer l\'engagement légal de subvention.'],
                        ],
                    ],
                ],
                [
                    'code'        => 'EXPEDIENTE',
                    'color'       => 'warning',
                    'is_terminal' => false,
                    'sort_order'  => 1,
                    'name'        => ['es' => 'Armado del Expediente', 'en' => 'Case Assembly', 'fr' => 'Constitution du dossier'],
                    'description' => ['es' => 'Preparación de formularios IMM y pruebas de relación familiar genuina.', 'en' => 'Preparation of IMM forms and evidence of genuine family relationship.', 'fr' => 'Préparation des formulaires IMM et preuves de la relation familiale authentique.'],
                    'templates'   => [
                        [
                            'code'                    => 'PRUEBAS_RELACION',
                            'is_required'             => true,
                            'blocks_stage_completion' => true,
                            'default_type'            => 'document',
                            'default_priority'        => 'high',
                            'due_offset_days'         => 20,
                            'name'        => ['es' => 'Pruebas de autenticidad de la relación', 'en' => 'Relationship authenticity evidence', 'fr' => 'Preuves d\'authenticité de la relation'],
                            'description' => ['es' => 'Fotos, comunicaciones, viajes conjuntos, documentos compartidos.', 'en' => 'Photos, communications, joint travel, shared documents.', 'fr' => 'Photos, communications, voyages communs, documents partagés.'],
                        ],
                        [
                            'code'                    => 'FORMULARIOS_IMM',
                            'is_required'             => true,
                            'blocks_stage_completion' => true,
                            'default_type'            => 'case_creation',
                            'default_priority'        => 'high',
                            'due_offset_days'         => 25,
                            'name'        => ['es' => 'Formularios IMM (1344, 5532, etc.)', 'en' => 'IMM forms (1344, 5532, etc.)', 'fr' => 'Formulaires IMM (1344, 5532, etc.)'],
                            'description' => ['es' => 'Completar todos los formularios requeridos para patrocinio familiar.', 'en' => 'Complete all required family sponsorship forms.', 'fr' => 'Compléter tous les formulaires requis pour le parrainage familial.'],
                        ],
                        [
                            'code'                    => 'CARGA_PORTAL',
                            'is_required'             => true,
                            'blocks_stage_completion' => true,
                            'default_type'            => 'filing',
                            'default_priority'        => 'urgent',
                            'due_offset_days'         => 30,
                            'name'        => ['es' => 'Carga en Permanent Residence Portal', 'en' => 'Upload to Permanent Residence Portal', 'fr' => 'Chargement sur le Portail de résidence permanente'],
                            'description' => ['es' => 'Enviar expediente completo a través del portal oficial de IRCC.', 'en' => 'Submit complete file through the official IRCC portal.', 'fr' => 'Soumettre le dossier complet via le portail officiel d\'IRCC.'],
                        ],
                    ],
                ],
                [
                    'code'        => 'POST_ENVIO',
                    'color'       => 'success',
                    'is_terminal' => true,
                    'sort_order'  => 2,
                    'name'        => ['es' => 'Seguimiento Post-Envío', 'en' => 'Post-Submission Follow-up', 'fr' => 'Suivi post-soumission'],
                    'description' => ['es' => 'Biométricos, examen médico, respuesta a PFL y recepción de eCOPR.', 'en' => 'Biometrics, medical exam, PFL response, and eCOPR receipt.', 'fr' => 'Biométriques, examen médical, réponse au PFL et réception de l\'eCOPR.'],
                    'templates'   => [
                        [
                            'code'                    => 'BIOMETRICOS_SP',
                            'is_required'             => false,
                            'blocks_stage_completion' => false,
                            'default_type'            => 'other',
                            'default_priority'        => 'high',
                            'due_offset_days'         => null,
                            'name'        => ['es' => 'Biométricos y examen médico', 'en' => 'Biometrics and medical exam', 'fr' => 'Biométriques et examen médical'],
                            'description' => ['es' => 'Coordinar toma de biométricos y examen médico del patrocinado.', 'en' => 'Coordinate biometrics and medical exam for the sponsored person.', 'fr' => 'Coordonner la prise des biométriques et l\'examen médical du parrainé.'],
                        ],
                        [
                            'code'                    => 'PFL_RESPUESTA',
                            'is_required'             => false,
                            'blocks_stage_completion' => false,
                            'default_type'            => 'filing',
                            'default_priority'        => 'urgent',
                            'due_offset_days'         => null,
                            'name'        => ['es' => 'Respuesta a PFL (Procedural Fairness Letter)', 'en' => 'PFL response', 'fr' => 'Réponse au PFL (lettre d\'équité procédurale)'],
                            'description' => ['es' => 'Preparar y enviar respuesta formal a cualquier PFL recibido.', 'en' => 'Prepare and submit formal response to any received PFL.', 'fr' => 'Préparer et soumettre la réponse formelle à tout PFL reçu.'],
                        ],
                        [
                            'code'                    => 'ECOPR_SP',
                            'is_required'             => true,
                            'blocks_stage_completion' => true,
                            'default_type'            => 'other',
                            'default_priority'        => 'high',
                            'due_offset_days'         => null,
                            'name'        => ['es' => 'Recepción de eCOPR', 'en' => 'eCOPR received', 'fr' => 'Réception de l\'eCOPR'],
                            'description' => ['es' => 'Notificar al cliente la recepción del eCOPR y registrar fecha de landing.', 'en' => 'Notify client of eCOPR receipt and record landing date.', 'fr' => 'Notifier le client de la réception de l\'eCOPR et enregistrer la date de débarquement.'],
                        ],
                    ],
                ],
            ],
        ];
    }

    // ── 5. Quebec RP (AR=ARRIMA, PQ=PEQ) ─────────────────────────────────────────

    private static function quebecRP(): array
    {
        return [
            'case_type_codes' => ['AR', 'PQ'],
            'stages' => [
                [
                    'code'        => 'PREREQ_QC',
                    'color'       => 'info',
                    'is_terminal' => false,
                    'sort_order'  => 0,
                    'name'        => ['es' => 'Pre-requisitos Quebec', 'en' => 'Quebec Pre-requirements', 'fr' => 'Pré-requis Québec'],
                    'description' => ['es' => 'Evaluación de francés, calificaciones y elegibilidad en el programa provincial.', 'en' => 'French assessment, qualifications, and provincial program eligibility.', 'fr' => 'Évaluation du français, qualifications et éligibilité au programme provincial.'],
                    'templates'   => [
                        [
                            'code'                    => 'FRANCES',
                            'is_required'             => true,
                            'blocks_stage_completion' => true,
                            'default_type'            => 'other',
                            'default_priority'        => 'high',
                            'due_offset_days'         => 30,
                            'name'        => ['es' => 'Evaluación de francés (TEF/TCF)', 'en' => 'French language assessment (TEF/TCF)', 'fr' => 'Évaluation du français (TEF/TCF)'],
                            'description' => ['es' => 'Inscripción y seguimiento del examen oficial de francés.', 'en' => 'Registration and follow-up for the official French language exam.', 'fr' => 'Inscription et suivi de l\'examen officiel de langue française.'],
                        ],
                        [
                            'code'                    => 'ELEGIBILIDAD_QC',
                            'is_required'             => true,
                            'blocks_stage_completion' => true,
                            'default_type'            => 'other',
                            'default_priority'        => 'high',
                            'due_offset_days'         => 14,
                            'name'        => ['es' => 'Verificación de elegibilidad provincial', 'en' => 'Provincial eligibility check', 'fr' => 'Vérification de l\'éligibilité provinciale'],
                            'description' => ['es' => 'Confirmar que el cliente cumple criterios del programa ARRIMA/PEQ.', 'en' => 'Confirm client meets ARRIMA/PEQ program criteria.', 'fr' => 'Confirmer que le client répond aux critères du programme ARRIMA/PEQ.'],
                        ],
                    ],
                ],
                [
                    'code'        => 'SOLICITUD_QC',
                    'color'       => 'secondary',
                    'is_terminal' => false,
                    'sort_order'  => 1,
                    'name'        => ['es' => 'Solicitud Provincial (MIDI)', 'en' => 'Provincial Application (MIDI)', 'fr' => 'Demande provinciale (MIDI)'],
                    'description' => ['es' => 'Preparación y envío de la solicitud ante el Ministerio de Inmigración de Quebec.', 'en' => 'Preparation and submission to Quebec Immigration Ministry.', 'fr' => 'Préparation et soumission de la demande au Ministère de l\'Immigration du Québec.'],
                    'templates'   => [
                        [
                            'code'                    => 'FORMULARIO_QC',
                            'is_required'             => true,
                            'blocks_stage_completion' => true,
                            'default_type'            => 'case_creation',
                            'default_priority'        => 'high',
                            'due_offset_days'         => 20,
                            'name'        => ['es' => 'Formulario de solicitud provincial', 'en' => 'Provincial application form', 'fr' => 'Formulaire de demande provinciale'],
                            'description' => ['es' => 'Completar y enviar el formulario al sistema ARRIMA o PEQ.', 'en' => 'Complete and submit the form in the ARRIMA or PEQ system.', 'fr' => 'Compléter et soumettre le formulaire dans le système ARRIMA ou PEQ.'],
                        ],
                        [
                            'code'                    => 'CSQ',
                            'is_required'             => true,
                            'blocks_stage_completion' => true,
                            'default_type'            => 'other',
                            'default_priority'        => 'high',
                            'due_offset_days'         => null,
                            'name'        => ['es' => 'Recepción del CSQ (Certificat de Sélection du Québec)', 'en' => 'CSQ receipt', 'fr' => 'Réception du CSQ'],
                            'description' => ['es' => 'Confirmar recepción del certificado provincial de selección.', 'en' => 'Confirm receipt of provincial selection certificate.', 'fr' => 'Confirmer la réception du certificat provincial de sélection.'],
                        ],
                    ],
                ],
                [
                    'code'        => 'SOLICITUD_FEDERAL',
                    'color'       => 'warning',
                    'is_terminal' => false,
                    'sort_order'  => 2,
                    'name'        => ['es' => 'Solicitud Federal de PR', 'en' => 'Federal PR Application', 'fr' => 'Demande fédérale de RP'],
                    'description' => ['es' => 'Envío de solicitud de Residencia Permanente al gobierno federal con el CSQ.', 'en' => 'Federal PR application submission with CSQ to IRCC.', 'fr' => 'Soumission de la demande de RP fédérale avec le CSQ à IRCC.'],
                    'templates'   => [
                        [
                            'code'                    => 'DOCS_FEDERALES',
                            'is_required'             => true,
                            'blocks_stage_completion' => true,
                            'default_type'            => 'document',
                            'default_priority'        => 'high',
                            'due_offset_days'         => 20,
                            'name'        => ['es' => 'Documentos federales requeridos', 'en' => 'Federal required documents', 'fr' => 'Documents fédéraux requis'],
                            'description' => ['es' => 'Certificados de policía, examen médico, fotos, formularios IRCC.', 'en' => 'Police certificates, medical exam, photos, IRCC forms.', 'fr' => 'Certificats de police, examen médical, photos, formulaires IRCC.'],
                        ],
                        [
                            'code'                    => 'ENVIO_FEDERAL',
                            'is_required'             => true,
                            'blocks_stage_completion' => true,
                            'default_type'            => 'filing',
                            'default_priority'        => 'urgent',
                            'due_offset_days'         => 25,
                            'name'        => ['es' => 'Envío federal de PR', 'en' => 'Federal PR submission', 'fr' => 'Soumission fédérale de RP'],
                            'description' => ['es' => 'Enviar solicitud completa a IRCC con CSQ incluido.', 'en' => 'Submit complete application to IRCC including CSQ.', 'fr' => 'Soumettre la demande complète à IRCC avec le CSQ.'],
                        ],
                    ],
                ],
                [
                    'code'        => 'ECOPR_QC',
                    'color'       => 'success',
                    'is_terminal' => true,
                    'sort_order'  => 3,
                    'name'        => ['es' => 'Decisión Final', 'en' => 'Final Decision', 'fr' => 'Décision finale'],
                    'description' => ['es' => 'Recepción del eCOPR y landing como Residente Permanente.', 'en' => 'eCOPR receipt and landing as Permanent Resident.', 'fr' => 'Réception de l\'eCOPR et débarquement en tant que résident permanent.'],
                    'templates'   => [
                        [
                            'code'                    => 'LANDING_QC',
                            'is_required'             => true,
                            'blocks_stage_completion' => true,
                            'default_type'            => 'other',
                            'default_priority'        => 'high',
                            'due_offset_days'         => null,
                            'name'        => ['es' => 'Landing y registro como PR', 'en' => 'Landing and PR registration', 'fr' => 'Débarquement et enregistrement comme RP'],
                            'description' => ['es' => 'Acompañar al cliente en el proceso de landing y confirmar estatus de PR.', 'en' => 'Accompany client through landing process and confirm PR status.', 'fr' => 'Accompagner le client dans le processus de débarquement et confirmer le statut de RP.'],
                        ],
                    ],
                ],
            ],
        ];
    }

    // ── 6. Federal RP + Programas Piloto (PI, HU) ────────────────────────────────

    private static function federalPilots(): array
    {
        return [
            'case_type_codes' => ['PI', 'HU'],
            'stages' => [
                [
                    'code'        => 'EVALUACION_FED',
                    'color'       => 'info',
                    'is_terminal' => false,
                    'sort_order'  => 0,
                    'name'        => ['es' => 'Evaluación de Elegibilidad', 'en' => 'Eligibility Assessment', 'fr' => 'Évaluation d\'éligibilité'],
                    'description' => ['es' => 'Revisión de criterios específicos del programa piloto o programa humanitario.', 'en' => 'Review of specific pilot program or humanitarian program criteria.', 'fr' => 'Révision des critères du programme pilote ou du programme humanitaire.'],
                    'templates'   => [
                        [
                            'code'                    => 'CRITERIOS_PROG',
                            'is_required'             => true,
                            'blocks_stage_completion' => true,
                            'default_type'            => 'other',
                            'default_priority'        => 'high',
                            'due_offset_days'         => 7,
                            'name'        => ['es' => 'Verificación de criterios del programa', 'en' => 'Program criteria verification', 'fr' => 'Vérification des critères du programme'],
                            'description' => ['es' => 'Confirmar que el cliente cumple todos los requisitos específicos del programa.', 'en' => 'Confirm client meets all program-specific requirements.', 'fr' => 'Confirmer que le client répond à toutes les exigences spécifiques du programme.'],
                        ],
                        [
                            'code'                    => 'DOCS_BASE',
                            'is_required'             => true,
                            'blocks_stage_completion' => true,
                            'default_type'            => 'document',
                            'default_priority'        => 'high',
                            'due_offset_days'         => 14,
                            'name'        => ['es' => 'Recopilación de documentos base', 'en' => 'Base document collection', 'fr' => 'Collecte des documents de base'],
                            'description' => ['es' => 'Pasaporte, biometría, pruebas de calificaciones y experiencia laboral.', 'en' => 'Passport, biometrics, qualification and work experience proofs.', 'fr' => 'Passeport, biométrie, preuves de qualifications et d\'expérience professionnelle.'],
                        ],
                    ],
                ],
                [
                    'code'        => 'PREPARACION_FED',
                    'color'       => 'warning',
                    'is_terminal' => false,
                    'sort_order'  => 1,
                    'name'        => ['es' => 'Preparación y Envío', 'en' => 'Preparation & Submission', 'fr' => 'Préparation et soumission'],
                    'description' => ['es' => 'Llenado de formularios IRCC y envío de la solicitud de RP.', 'en' => 'IRCC form completion and PR application submission.', 'fr' => 'Remplissage des formulaires IRCC et soumission de la demande de RP.'],
                    'templates'   => [
                        [
                            'code'                    => 'FORMULARIOS_FED',
                            'is_required'             => true,
                            'blocks_stage_completion' => true,
                            'default_type'            => 'case_creation',
                            'default_priority'        => 'high',
                            'due_offset_days'         => 20,
                            'name'        => ['es' => 'Formularios IRCC', 'en' => 'IRCC forms', 'fr' => 'Formulaires IRCC'],
                            'description' => ['es' => 'Completar todos los formularios de solicitud de residencia permanente.', 'en' => 'Complete all permanent residence application forms.', 'fr' => 'Compléter tous les formulaires de demande de résidence permanente.'],
                        ],
                        [
                            'code'                    => 'PAGO_FED',
                            'is_required'             => true,
                            'blocks_stage_completion' => true,
                            'default_type'            => 'accounting',
                            'default_priority'        => 'high',
                            'due_offset_days'         => 22,
                            'name'        => ['es' => 'Pago de tarifas de procesamiento', 'en' => 'Processing fee payment', 'fr' => 'Paiement des frais de traitement'],
                            'description' => ['es' => 'Realizar el pago oficial de tarifas de PR ante IRCC.', 'en' => 'Complete official PR fee payment to IRCC.', 'fr' => 'Effectuer le paiement officiel des frais de RP à IRCC.'],
                        ],
                        [
                            'code'                    => 'ENVIO_FED',
                            'is_required'             => true,
                            'blocks_stage_completion' => true,
                            'default_type'            => 'filing',
                            'default_priority'        => 'urgent',
                            'due_offset_days'         => 25,
                            'name'        => ['es' => 'Envío de solicitud', 'en' => 'Submit application', 'fr' => 'Soumission de la demande'],
                            'description' => ['es' => 'Enviar la solicitud completa al sistema de IRCC.', 'en' => 'Submit the complete application to the IRCC system.', 'fr' => 'Soumettre la demande complète dans le système IRCC.'],
                        ],
                    ],
                ],
                [
                    'code'        => 'DECISION_FED',
                    'color'       => 'success',
                    'is_terminal' => true,
                    'sort_order'  => 2,
                    'name'        => ['es' => 'Seguimiento y Decisión', 'en' => 'Follow-up & Decision', 'fr' => 'Suivi et décision'],
                    'description' => ['es' => 'Monitoreo del expediente, biométricos y recepción de eCOPR.', 'en' => 'Case monitoring, biometrics, and eCOPR receipt.', 'fr' => 'Surveillance du dossier, biométriques et réception de l\'eCOPR.'],
                    'templates'   => [
                        [
                            'code'                    => 'EXAMEN_MED_FED',
                            'is_required'             => false,
                            'blocks_stage_completion' => false,
                            'default_type'            => 'other',
                            'default_priority'        => 'high',
                            'due_offset_days'         => null,
                            'name'        => ['es' => 'Examen médico', 'en' => 'Medical exam', 'fr' => 'Examen médical'],
                            'description' => ['es' => 'Coordinar examen con médico designado si es requerido.', 'en' => 'Coordinate exam with designated physician if required.', 'fr' => 'Coordonner l\'examen avec un médecin désigné si requis.'],
                        ],
                        [
                            'code'                    => 'ECOPR_FED',
                            'is_required'             => true,
                            'blocks_stage_completion' => true,
                            'default_type'            => 'other',
                            'default_priority'        => 'high',
                            'due_offset_days'         => null,
                            'name'        => ['es' => 'Recepción de eCOPR y landing', 'en' => 'eCOPR receipt and landing', 'fr' => 'Réception de l\'eCOPR et débarquement'],
                            'description' => ['es' => 'Confirmar landing y notificar al cliente su estatus de PR.', 'en' => 'Confirm landing and notify client of their PR status.', 'fr' => 'Confirmer le débarquement et notifier le client de son statut de RP.'],
                        ],
                    ],
                ],
            ],
        ];
    }

    // ── 7. Refugiados y Apelaciones (AS, AP, FC, ER) ─────────────────────────────

    private static function refugeeAppeals(): array
    {
        return [
            'case_type_codes' => ['AS', 'AP', 'FC', 'ER'],
            'stages' => [
                [
                    'code'        => 'PRESENTACION',
                    'color'       => 'danger',
                    'is_terminal' => false,
                    'sort_order'  => 0,
                    'name'        => ['es' => 'Presentación del Caso', 'en' => 'Case Presentation', 'fr' => 'Présentation du dossier'],
                    'description' => ['es' => 'Recopilación de evidencia, preparación de fundamentos y formulario de refugiado/apelación.', 'en' => 'Evidence collection, legal basis preparation, and refugee/appeal form.', 'fr' => 'Collecte de preuves, préparation des fondements juridiques et formulaire de réfugié/appel.'],
                    'templates'   => [
                        [
                            'code'                    => 'ENTREVISTA_CLIENTE',
                            'is_required'             => true,
                            'blocks_stage_completion' => true,
                            'default_type'            => 'other',
                            'default_priority'        => 'urgent',
                            'due_offset_days'         => 3,
                            'name'        => ['es' => 'Entrevista detallada con el cliente', 'en' => 'Detailed client interview', 'fr' => 'Entrevue détaillée avec le client'],
                            'description' => ['es' => 'Documentar historia de persecución, riesgo y fundamentos del caso.', 'en' => 'Document persecution history, risk factors, and case grounds.', 'fr' => 'Documenter l\'histoire de persécution, les facteurs de risque et les fondements du dossier.'],
                        ],
                        [
                            'code'                    => 'EVIDENCIA_PAIS',
                            'is_required'             => true,
                            'blocks_stage_completion' => true,
                            'default_type'            => 'document',
                            'default_priority'        => 'high',
                            'due_offset_days'         => 10,
                            'name'        => ['es' => 'Documentación de condiciones del país de origen', 'en' => 'Country of origin conditions documentation', 'fr' => 'Documentation des conditions du pays d\'origine'],
                            'description' => ['es' => 'Informes COI, reportes UNHCR, artículos periodísticos y pruebas personales.', 'en' => 'COI reports, UNHCR reports, news articles, and personal evidence.', 'fr' => 'Rapports COI, rapports UNHCR, articles de presse et preuves personnelles.'],
                        ],
                        [
                            'code'                    => 'FORMULARIO_BASIS',
                            'is_required'             => true,
                            'blocks_stage_completion' => true,
                            'default_type'            => 'case_creation',
                            'default_priority'        => 'urgent',
                            'due_offset_days'         => 15,
                            'name'        => ['es' => 'Formulario Basis of Claim (BOC)', 'en' => 'Basis of Claim (BOC) form', 'fr' => 'Formulaire Exposé des motifs (BOC)'],
                            'description' => ['es' => 'Redactar y revisar el BOC o escrito de apelación detallado.', 'en' => 'Draft and review the BOC or detailed appeal brief.', 'fr' => 'Rédiger et réviser le BOC ou le mémoire d\'appel détaillé.'],
                        ],
                    ],
                ],
                [
                    'code'        => 'AUDIENCIA',
                    'color'       => 'warning',
                    'is_terminal' => false,
                    'sort_order'  => 1,
                    'name'        => ['es' => 'Audiencia / Revisión', 'en' => 'Hearing / Review', 'fr' => 'Audience / Révision'],
                    'description' => ['es' => 'Preparación final y representación en la audiencia ante la SPR, SAR, Corte Federal o ERAR.', 'en' => 'Final preparation and representation at RPD, RAD, Federal Court, or PRRA hearing.', 'fr' => 'Préparation finale et représentation à l\'audience SPR, SAR, Cour fédérale ou ERAR.'],
                    'templates'   => [
                        [
                            'code'                    => 'PREP_AUDIENCIA',
                            'is_required'             => true,
                            'blocks_stage_completion' => true,
                            'default_type'            => 'other',
                            'default_priority'        => 'urgent',
                            'due_offset_days'         => 7,
                            'name'        => ['es' => 'Preparación para audiencia', 'en' => 'Hearing preparation', 'fr' => 'Préparation pour l\'audience'],
                            'description' => ['es' => 'Sesiones de preparación con el cliente, simulacro de preguntas y revisión de evidencia.', 'en' => 'Prep sessions with client, mock questions, and evidence review.', 'fr' => 'Sessions de préparation avec le client, questions simulées et révision des preuves.'],
                        ],
                        [
                            'code'                    => 'REPRESENTACION',
                            'is_required'             => true,
                            'blocks_stage_completion' => true,
                            'default_type'            => 'other',
                            'default_priority'        => 'urgent',
                            'due_offset_days'         => null,
                            'name'        => ['es' => 'Representación en la audiencia', 'en' => 'Hearing representation', 'fr' => 'Représentation à l\'audience'],
                            'description' => ['es' => 'Asistir y representar al cliente en la audiencia oficial.', 'en' => 'Attend and represent the client at the official hearing.', 'fr' => 'Assister et représenter le client lors de l\'audience officielle.'],
                        ],
                    ],
                ],
                [
                    'code'        => 'DECISION_REF',
                    'color'       => 'success',
                    'is_terminal' => true,
                    'sort_order'  => 2,
                    'name'        => ['es' => 'Decisión y Cierre', 'en' => 'Decision & Closure', 'fr' => 'Décision et clôture'],
                    'description' => ['es' => 'Recepción de la decisión, notificación al cliente y acciones post-decisión.', 'en' => 'Decision receipt, client notification, and post-decision actions.', 'fr' => 'Réception de la décision, notification du client et actions post-décision.'],
                    'templates'   => [
                        [
                            'code'                    => 'DECISION_RECIBIDA',
                            'is_required'             => true,
                            'blocks_stage_completion' => true,
                            'default_type'            => 'other',
                            'default_priority'        => 'urgent',
                            'due_offset_days'         => null,
                            'name'        => ['es' => 'Recepción y análisis de la decisión', 'en' => 'Decision received and analyzed', 'fr' => 'Décision reçue et analysée'],
                            'description' => ['es' => 'Notificar al cliente, explicar alcances de la decisión y próximos pasos.', 'en' => 'Notify client, explain decision implications, and next steps.', 'fr' => 'Notifier le client, expliquer les implications de la décision et les prochaines étapes.'],
                        ],
                        [
                            'code'                    => 'POST_DECISION',
                            'is_required'             => false,
                            'blocks_stage_completion' => false,
                            'default_type'            => 'other',
                            'default_priority'        => 'high',
                            'due_offset_days'         => 15,
                            'name'        => ['es' => 'Acciones post-decisión (si aplica)', 'en' => 'Post-decision actions (if applicable)', 'fr' => 'Actions post-décision (si applicable)'],
                            'description' => ['es' => 'Iniciar proceso de apelación o regularización de estatus según el resultado.', 'en' => 'Initiate appeal or status regularization based on the outcome.', 'fr' => 'Initier le processus d\'appel ou de régularisation du statut selon le résultat.'],
                        ],
                    ],
                ],
            ],
        ];
    }

    // ── 8. Ciudadanía (CZ, CC) ────────────────────────────────────────────────────

    private static function citizenship(): array
    {
        return [
            'case_type_codes' => ['CZ', 'CC'],
            'stages' => [
                [
                    'code'        => 'REQUISITOS_CIT',
                    'color'       => 'info',
                    'is_terminal' => false,
                    'sort_order'  => 0,
                    'name'        => ['es' => 'Verificación de Requisitos', 'en' => 'Requirements Verification', 'fr' => 'Vérification des exigences'],
                    'description' => ['es' => 'Revisión de tiempo de residencia física, declaración de impuestos y conocimiento de inglés/francés.', 'en' => 'Review of physical presence, tax filing, and English/French knowledge.', 'fr' => 'Révision de la présence physique, des déclarations fiscales et des connaissances en anglais/français.'],
                    'templates'   => [
                        [
                            'code'                    => 'PRESENCIA_FISICA',
                            'is_required'             => true,
                            'blocks_stage_completion' => true,
                            'default_type'            => 'document',
                            'default_priority'        => 'high',
                            'due_offset_days'         => 7,
                            'name'        => ['es' => 'Cálculo de presencia física', 'en' => 'Physical presence calculation', 'fr' => 'Calcul de la présence physique'],
                            'description' => ['es' => 'Verificar y documentar 1095 días de presencia en los últimos 5 años.', 'en' => 'Verify and document 1095 days of presence in the last 5 years.', 'fr' => 'Vérifier et documenter 1095 jours de présence au cours des 5 dernières années.'],
                        ],
                        [
                            'code'                    => 'IMPUESTOS',
                            'is_required'             => true,
                            'blocks_stage_completion' => true,
                            'default_type'            => 'document',
                            'default_priority'        => 'high',
                            'due_offset_days'         => 10,
                            'name'        => ['es' => 'Declaraciones de impuestos (T1)', 'en' => 'Tax returns (T1)', 'fr' => 'Déclarations fiscales (T1)'],
                            'description' => ['es' => 'Obtener copias de las declaraciones de impuestos de los últimos 3 años.', 'en' => 'Obtain tax return copies for the last 3 years.', 'fr' => 'Obtenir les copies des déclarations fiscales des 3 dernières années.'],
                        ],
                    ],
                ],
                [
                    'code'        => 'SOLICITUD_CIT',
                    'color'       => 'warning',
                    'is_terminal' => false,
                    'sort_order'  => 1,
                    'name'        => ['es' => 'Preparación de Solicitud', 'en' => 'Application Preparation', 'fr' => 'Préparation de la demande'],
                    'description' => ['es' => 'Llenado del formulario CIT 0002 y recopilación de documentos adjuntos.', 'en' => 'Complete CIT 0002 form and gather supporting documents.', 'fr' => 'Remplissage du formulaire CIT 0002 et collecte des documents joints.'],
                    'templates'   => [
                        [
                            'code'                    => 'FORMULARIO_CIT',
                            'is_required'             => true,
                            'blocks_stage_completion' => true,
                            'default_type'            => 'case_creation',
                            'default_priority'        => 'high',
                            'due_offset_days'         => 15,
                            'name'        => ['es' => 'Formulario CIT 0002', 'en' => 'CIT 0002 form', 'fr' => 'Formulaire CIT 0002'],
                            'description' => ['es' => 'Completar y revisar el formulario de solicitud de ciudadanía.', 'en' => 'Complete and review the citizenship application form.', 'fr' => 'Compléter et réviser le formulaire de demande de citoyenneté.'],
                        ],
                        [
                            'code'                    => 'FOTOS_DOCS_CIT',
                            'is_required'             => true,
                            'blocks_stage_completion' => true,
                            'default_type'            => 'document',
                            'default_priority'        => 'medium',
                            'due_offset_days'         => 16,
                            'name'        => ['es' => 'Fotos y documentos adjuntos', 'en' => 'Photos and supporting documents', 'fr' => 'Photos et documents joints'],
                            'description' => ['es' => 'Fotos de formato oficial, PR card, pasaporte y prueba de presencia física.', 'en' => 'Official format photos, PR card, passport, and physical presence proof.', 'fr' => 'Photos au format officiel, carte RP, passeport et preuve de présence physique.'],
                        ],
                        [
                            'code'                    => 'ENVIO_CIT',
                            'is_required'             => true,
                            'blocks_stage_completion' => true,
                            'default_type'            => 'filing',
                            'default_priority'        => 'urgent',
                            'due_offset_days'         => 18,
                            'name'        => ['es' => 'Envío de solicitud de ciudadanía', 'en' => 'Citizenship application submission', 'fr' => 'Soumission de la demande de citoyenneté'],
                            'description' => ['es' => 'Enviar el paquete completo al IRCC con acuse de recibo.', 'en' => 'Submit the complete package to IRCC with receipt confirmation.', 'fr' => 'Soumettre le dossier complet à IRCC avec accusé de réception.'],
                        ],
                    ],
                ],
                [
                    'code'        => 'CEREMONIA',
                    'color'       => 'success',
                    'is_terminal' => true,
                    'sort_order'  => 2,
                    'name'        => ['es' => 'Test y Ceremonia', 'en' => 'Test & Ceremony', 'fr' => 'Test et cérémonie'],
                    'description' => ['es' => 'Preparación para el examen de conocimiento y asistencia a la ceremonia de juramento.', 'en' => 'Citizenship test preparation and oath ceremony attendance.', 'fr' => 'Préparation à l\'examen de connaissance et participation à la cérémonie de serment.'],
                    'templates'   => [
                        [
                            'code'                    => 'EXAMEN_CIT',
                            'is_required'             => false,
                            'blocks_stage_completion' => false,
                            'default_type'            => 'other',
                            'default_priority'        => 'high',
                            'due_offset_days'         => null,
                            'name'        => ['es' => 'Preparación y examen de conocimiento', 'en' => 'Knowledge test preparation', 'fr' => 'Préparation et examen de connaissance'],
                            'description' => ['es' => 'Proveer materiales de estudio y seguimiento al resultado del examen.', 'en' => 'Provide study materials and follow up on test result.', 'fr' => 'Fournir les matériaux d\'étude et suivre le résultat de l\'examen.'],
                        ],
                        [
                            'code'                    => 'JURAMENTO',
                            'is_required'             => true,
                            'blocks_stage_completion' => true,
                            'default_type'            => 'other',
                            'default_priority'        => 'high',
                            'due_offset_days'         => null,
                            'name'        => ['es' => 'Ceremonia de juramento', 'en' => 'Oath ceremony', 'fr' => 'Cérémonie de serment'],
                            'description' => ['es' => 'Confirmar asistencia a la ceremonia y registro de obtención de ciudadanía.', 'en' => 'Confirm ceremony attendance and citizenship acquisition record.', 'fr' => 'Confirmer la participation à la cérémonie et enregistrement de l\'obtention de la citoyenneté.'],
                        ],
                    ],
                ],
            ],
        ];
    }

    // ── 9. Documentos de Viaje (PA=Passport, TD=Travel Document) ─────────────────

    private static function travelDocuments(): array
    {
        return [
            'case_type_codes' => ['PA', 'TD'],
            'stages' => [
                [
                    'code'        => 'SOLICITUD_DOC',
                    'color'       => 'info',
                    'is_terminal' => false,
                    'sort_order'  => 0,
                    'name'        => ['es' => 'Solicitud', 'en' => 'Application', 'fr' => 'Demande'],
                    'description' => ['es' => 'Recopilación de documentos y envío de la solicitud de pasaporte o documento de viaje.', 'en' => 'Document collection and submission of passport or travel document application.', 'fr' => 'Collecte des documents et soumission de la demande de passeport ou de document de voyage.'],
                    'templates'   => [
                        [
                            'code'                    => 'FORMULARIO_DOC',
                            'is_required'             => true,
                            'blocks_stage_completion' => true,
                            'default_type'            => 'case_creation',
                            'default_priority'        => 'high',
                            'due_offset_days'         => 5,
                            'name'        => ['es' => 'Formulario de solicitud', 'en' => 'Application form', 'fr' => 'Formulaire de demande'],
                            'description' => ['es' => 'Completar el formulario PPTC 153 u otro aplicable.', 'en' => 'Complete PPTC 153 or other applicable form.', 'fr' => 'Compléter le formulaire PPTC 153 ou autre formulaire applicable.'],
                        ],
                        [
                            'code'                    => 'FOTOS_ID_DOC',
                            'is_required'             => true,
                            'blocks_stage_completion' => true,
                            'default_type'            => 'document',
                            'default_priority'        => 'medium',
                            'due_offset_days'         => 5,
                            'name'        => ['es' => 'Fotos y documentos de identidad', 'en' => 'Photos and identity documents', 'fr' => 'Photos et documents d\'identité'],
                            'description' => ['es' => 'Fotos de pasaporte, documentos de estatus migratorio o ciudadanía.', 'en' => 'Passport photos, immigration status or citizenship documents.', 'fr' => 'Photos pour passeport, documents de statut migratoire ou citoyenneté.'],
                        ],
                        [
                            'code'                    => 'ENVIO_DOC',
                            'is_required'             => true,
                            'blocks_stage_completion' => true,
                            'default_type'            => 'filing',
                            'default_priority'        => 'high',
                            'due_offset_days'         => 7,
                            'name'        => ['es' => 'Envío de solicitud', 'en' => 'Submit application', 'fr' => 'Soumission de la demande'],
                            'description' => ['es' => 'Enviar solicitud completa con las tarifas correspondientes.', 'en' => 'Submit complete application with applicable fees.', 'fr' => 'Soumettre la demande complète avec les frais correspondants.'],
                        ],
                    ],
                ],
                [
                    'code'        => 'ENTREGA_DOC',
                    'color'       => 'success',
                    'is_terminal' => true,
                    'sort_order'  => 1,
                    'name'        => ['es' => 'Procesamiento y Entrega', 'en' => 'Processing & Delivery', 'fr' => 'Traitement et livraison'],
                    'description' => ['es' => 'Seguimiento del procesamiento y entrega del documento al cliente.', 'en' => 'Processing follow-up and document delivery to client.', 'fr' => 'Suivi du traitement et livraison du document au client.'],
                    'templates'   => [
                        [
                            'code'                    => 'SEGUIMIENTO_DOC',
                            'is_required'             => false,
                            'blocks_stage_completion' => false,
                            'default_type'            => 'other',
                            'default_priority'        => 'medium',
                            'due_offset_days'         => null,
                            'name'        => ['es' => 'Seguimiento del procesamiento', 'en' => 'Processing follow-up', 'fr' => 'Suivi du traitement'],
                            'description' => ['es' => 'Verificar el estado de la solicitud periódicamente hasta su aprobación.', 'en' => 'Periodically check application status until approval.', 'fr' => 'Vérifier périodiquement le statut de la demande jusqu\'à son approbation.'],
                        ],
                        [
                            'code'                    => 'ENTREGA_FINAL',
                            'is_required'             => true,
                            'blocks_stage_completion' => true,
                            'default_type'            => 'other',
                            'default_priority'        => 'medium',
                            'due_offset_days'         => null,
                            'name'        => ['es' => 'Entrega del documento al cliente', 'en' => 'Document delivered to client', 'fr' => 'Document remis au client'],
                            'description' => ['es' => 'Confirmar recepción del pasaporte o documento de viaje y cerrar el expediente.', 'en' => 'Confirm receipt of passport or travel document and close the case.', 'fr' => 'Confirmer la réception du passeport ou du document de voyage et clôturer le dossier.'],
                        ],
                    ],
                ],
            ],
        ];
    }
}
