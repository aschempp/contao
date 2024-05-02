<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Security\Voter\DataContainer;

use Contao\CoreBundle\Security\ContaoCorePermissions;
use Contao\CoreBundle\Security\DataContainer\CreateAction;
use Contao\CoreBundle\Security\DataContainer\DeleteAction;
use Contao\CoreBundle\Security\DataContainer\ReadAction;
use Contao\CoreBundle\Security\DataContainer\UpdateAction;
use Contao\CoreBundle\Security\DataContainer\UploadAction;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\CacheableVoterInterface;
use Webmozart\PathUtil\Path;

/**
 * @internal
 */
class FilesAccessVoter implements CacheableVoterInterface
{
    use FieldsOfTableTrait;

    public function __construct(
        private readonly AccessDecisionManagerInterface $accessDecisionManager,
        private readonly string $projectDir,
    ) {
    }

    public function supportsAttribute(string $attribute): bool
    {
        return $attribute === ContaoCorePermissions::DC_PREFIX.'tl_files';
    }

    public function supportsType(string $subjectType): bool
    {
        return \in_array($subjectType, [CreateAction::class, ReadAction::class, UpdateAction::class, DeleteAction::class, UploadAction::class], true);
    }

    public function vote(TokenInterface $token, $subject, array $attributes): int
    {
        if (!array_filter($attributes, $this->supportsAttribute(...))) {
            return self::ACCESS_ABSTAIN;
        }

        if ($this->accessDecisionManager->decide($token, ['ROLE_ADMIN'])) {
            return self::ACCESS_ABSTAIN;
        }

        $isGranted = match(true) {
            $subject instanceof ReadAction => $this->isMounted($token, $subject->getCurrentId()),
            $subject instanceof CreateAction => $this->canCreate($token, $subject),
            $subject instanceof UpdateAction => $this->canUpdate($token, $subject),
            $subject instanceof DeleteAction => $this->canDelete($token, $subject),
            $subject instanceof UploadAction => $this->canUpload($token, $subject),
            default => true,
        };

        return $isGranted ? self::ACCESS_ABSTAIN : self::ACCESS_DENIED;
    }

    private function isMounted(TokenInterface $token, string $path): bool
    {
        if (is_file($this->projectDir.'/'.$path)) {
            $path = \dirname($path);
        }

        return $this->accessDecisionManager->decide($token, [ContaoCorePermissions::USER_CAN_ACCESS_PATH], $path);
    }

    private function canCreate(TokenInterface $token, CreateAction $action): bool
    {
        // Global "create folder" or "upload file" operations do not have a target
        if (empty($action->getNewPid() ?: $action->getNewId())) {
            if ('folder' === ($action->getNew()['type'] ?? null) && !$this->canEditFieldsOfTable($token, $action->getDataSource())) {
                return false;
            }

            return $this->accessDecisionManager->decide($token, [ContaoCorePermissions::USER_CAN_UPLOAD_FILES]);
        }

        if (!$this->isMounted($token, $action->getNewPid() ?: $action->getNewId())) {
            return false;
        }

        // Copy file or folder
        if (array_key_exists('pid', $action->getNew() ?? []) && null === $action->getNewPid()) {
            return $this->accessDecisionManager->decide($token, [ContaoCorePermissions::USER_CAN_RENAME_FILE]);
        }

        return $this->accessDecisionManager->decide($token, [ContaoCorePermissions::USER_CAN_UPLOAD_FILES]);
    }

    private function canUpdate(TokenInterface $token, UpdateAction $action): bool
    {
        if (!$this->isMounted($token, $action->getCurrentId())) {
            return false;
        }

        // Move file or folder
        if (array_key_exists('pid', $action->getNew() ?? []) && null === $action->getNewPid()) {
            return $this->accessDecisionManager->decide($token, [ContaoCorePermissions::USER_CAN_RENAME_FILE]);
        }

        return $this->canEditFieldsOfTable($token, $action->getDataSource());
    }

    private function canDelete(TokenInterface $token, DeleteAction $action): bool
    {
        if (!$this->isMounted($token, $action->getCurrentId())) {
            return false;
        }

        if (is_dir($this->projectDir.'/'.$action->getCurrentId())) {
            return $this->accessDecisionManager->decide($token, [ContaoCorePermissions::USER_CAN_DELETE_RECURSIVELY])
                || (
                    !Finder::create()->in($this->projectDir.'/'.$action->getCurrentId())->hasResults()
                    && $this->accessDecisionManager->decide($token, [ContaoCorePermissions::USER_CAN_DELETE_FILE])
                );
        }

        return $this->accessDecisionManager->decide($token, [ContaoCorePermissions::USER_CAN_DELETE_FILE])
            || $this->accessDecisionManager->decide($token, [ContaoCorePermissions::USER_CAN_DELETE_RECURSIVELY]);
    }

    private function canUpload(TokenInterface $token, UploadAction $action): bool
    {
        if (!$this->isMounted($token, $action->getCurrentId())) {
            return false;
        }

        return $this->accessDecisionManager->decide($token, [ContaoCorePermissions::USER_CAN_EDIT_FILE]);
    }
}
