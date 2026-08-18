<?php

namespace App\Entity;

class ConstantsClass
{
    // Differents rôles en fonction du poste occupé
    public const ROLE_ADMIN = 'ROLE_ADMIN';
    public const ROLE_SUPER_ADMIN = 'ROLE_SUPER_ADMIN';
    public const ROLE_TALENT = 'ROLE_TALENT';
    public const ROLE_COMPANY = 'ROLE_COMPANY';
    public const ROLE_PARTICULIER = 'ROLE_PARTICULIER';
    public const ROLE_MODERATEUR = 'ROLE_MODERATEUR';


    public const TYPE_COMPANY = 'company';
    public const TYPE_TALENT = 'talent';
    public const TYPE_PARTICULIER = 'particulier';

    
    public const ACTION_USER_SUSPENDED = 'USER_SUSPENDED';
    public const ACTION_JOB_HIDDEN = 'JOB_HIDDEN';
    public const ACTION_WARNING_SENT = 'WARNING_SENT';
    public const ACTION_REPORT_RESOLVED = 'REPORT_RESOLVED';
    
}