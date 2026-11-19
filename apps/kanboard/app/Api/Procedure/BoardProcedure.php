<?php

namespace Kanboard\Api\Procedure;

use Kanboard\Api\Authorization\ProjectAuthorization;

/**
 * Board API controller
 *
 * @package  Kanboard\Api\Procedure
 * @author   Frederic Guillot
 */
class BoardProcedure extends BaseProcedure
{
    public function getBoard($project_id)
    {
        ProjectAuthorization::getInstance($this->container)->check($this->getClassName(), 'getBoard', $project_id);
        
        $this->taskFinderModel->withLightQuery();

        return $this->boardFormatter
            ->withProjectId($project_id)
            ->withQuery($this->taskFinderModel->getExtendedQuery())
            ->withTaskLimit(PHP_INT_MAX)
            ->format();
    }
}
