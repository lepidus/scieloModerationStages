<?php

class FakeModerationStagesRequest
{
    private $isPost;
    private $csrfIsValid;

    public function __construct(bool $isPost, bool $csrfIsValid)
    {
        $this->isPost = $isPost;
        $this->csrfIsValid = $csrfIsValid;
    }

    public function isPost()
    {
        return $this->isPost;
    }

    public function checkCSRF()
    {
        return $this->csrfIsValid;
    }
}
