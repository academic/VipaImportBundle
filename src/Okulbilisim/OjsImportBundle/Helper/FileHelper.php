<?php

namespace Okulbilisim\OjsImportBundle\Helper;

class FileHelper {
    public static $mimeToExtMap = [
        'application/pdf'    => 'pdf',
        'image/jpeg'         => 'jpg',
        'application/msword' => 'doc',
        'application/zip'    => 'zip',
        'application/text-plain:formatted' => 'txt',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx'
    ];
}