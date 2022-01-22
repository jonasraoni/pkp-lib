<?php

/**
 * @file api/v1/vocabs/PKPVocabHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPVocabHandler
 * @ingroup api_v1_vocab
 *
 * @brief Handle API requests for controlled vocab operations.
 *
 */

use APP\core\Application;
use PKP\db\DAORegistry;
use PKP\facades\Locale;
use PKP\handler\APIHandler;
use PKP\plugins\HookRegistry;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\Role;

class PKPVocabHandler extends APIHandler
{
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->_handlerPath = 'vocabs';
        $this->_endpoints = [
            'GET' => [
                [
                    'pattern' => $this->getEndpointPattern(),
                    'handler' => [$this, 'getMany'],
                    'roles' => [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT, Role::ROLE_ID_AUTHOR],
                ],
            ],
        ];
        parent::__construct();
    }

    //
    // Implement methods from PKPHandler
    //
    public function authorize($request, &$args, $roleAssignments)
    {
        $this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));

        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Get the controlled vocab entries available in this context
     *
     * @param Request $slimRequest Slim request object
     * @param Response $response object
     * @param array $args arguments
     *
     * @return Response
     */
    public function getMany($slimRequest, $response, $args)
    {
        $request = Application::get()->getRequest();
        $context = $request->getContext();

        if (!$context) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        $requestParams = $slimRequest->getQueryParams();

        $vocab = !empty($requestParams['vocab']) ? $requestParams['vocab'] : '';
        $locale = !empty($requestParams['locale']) ? $requestParams['locale'] : Locale::getLocale();

        if (!in_array($locale, $context->getData('supportedSubmissionLocales'))) {
            return $response->withStatus(400)->withJsonError('api.vocabs.400.localeNotSupported', ['locale' => $locale]);
        }

        switch ($vocab) {
            case \PKP\submission\SubmissionKeywordDAO::CONTROLLED_VOCAB_SUBMISSION_KEYWORD:
                $submissionKeywordEntryDao = DAORegistry::getDAO('SubmissionKeywordEntryDAO'); /** @var SubmissionKeywordEntryDAO $submissionKeywordEntryDao */
                $entries = $submissionKeywordEntryDao->getByContextId($vocab, $context->getId(), $locale)->toArray();
                break;
            case \PKP\submission\SubmissionSubjectDAO::CONTROLLED_VOCAB_SUBMISSION_SUBJECT:
                $submissionSubjectEntryDao = DAORegistry::getDAO('SubmissionSubjectEntryDAO'); /** @var SubmissionSubjectEntryDAO $submissionSubjectEntryDao */
                $entries = $submissionSubjectEntryDao->getByContextId($vocab, $context->getId(), $locale)->toArray();
                break;
            case \PKP\submission\SubmissionDisciplineDAO::CONTROLLED_VOCAB_SUBMISSION_DISCIPLINE:
                $submissionDisciplineEntryDao = DAORegistry::getDAO('SubmissionDisciplineEntryDAO'); /** @var SubmissionDisciplineEntryDAO $submissionDisciplineEntryDao */
                $entries = $submissionDisciplineEntryDao->getByContextId($vocab, $context->getId(), $locale)->toArray();
                break;
            case \PKP\submission\SubmissionLanguageDAO::CONTROLLED_VOCAB_SUBMISSION_LANGUAGE:
                $languageNames = [];
                foreach (Locale::getLanguages() as $language) {
                    if (!$language->getAlpha2() || $language->getType() != 'L' || $language->getScope() != 'I') {
                        continue;
                    }
                    $languageNames[] = $language->getLocalName();
                }
                asort($languageNames);
                return $response->withJson($languageNames, 200);
            case \PKP\submission\SubmissionAgencyDAO::CONTROLLED_VOCAB_SUBMISSION_AGENCY:
                $submissionAgencyEntryDao = DAORegistry::getDAO('SubmissionAgencyEntryDAO'); /** @var SubmissionAgencyEntryDAO $submissionAgencyEntryDao */
                $entries = $submissionAgencyEntryDao->getByContextId($vocab, $context->getId(), $locale)->toArray();
                break;
            default:
                $entries = [];
                HookRegistry::call('API::vocabs::getMany', [$vocab, &$entries, $slimRequest, $response, $this->request]);
        }

        $data = [];
        foreach ($entries as $entry) {
            $data[] = $entry->getData($vocab, $locale);
        }

        $data = array_values(array_unique($data));

        return $response->withJson($data, 200);
    }
}
