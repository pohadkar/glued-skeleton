<?php

declare(strict_types=1);

namespace Glued\Worklog\Controllers;

use Carbon\Carbon;
use Glued\Core\Controllers\AbstractTwigController;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Respect\Validation\Validator as v;
use Sabre\VObject;
use Spatie\Browsershot\Browsershot;

class WorklogController extends AbstractTwigController
{
    /**
     * @param Request  $request
     * @param Response $response
     * @param array    $args
     *
     * @return Response
     */
    public function me_get(Request $request, Response $response, array $args = []): Response
    {
        $collection = $this->db->getOne('t_calendar_uris');
        $ical = json_decode((string)$collection['c_json'], true)['ical'];
   
        return $this->render($response, 'Worklog/Views/i.twig', [
            'pageTitle' => 'Worklog'
        ]);
    }

    public function me_post(Request $request, Response $response, array $args = []): Response
    {
        
        //echo $_SESSION['core_auth_id'];
        return $response->withJson($request->getParsedBody());
    }
}
