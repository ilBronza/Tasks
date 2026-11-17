<?php

namespace IlBronza\Tasks\Http\Controllers;

use IlBronza\CRUD\Traits\CRUDCreateStoreTrait;
use IlBronza\CRUD\Traits\CRUDEditUpdateTrait;
use IlBronza\CRUD\Traits\CRUDIndexTrait;
use IlBronza\CRUD\Traits\CRUDPlainIndexTrait;
use IlBronza\CRUD\Traits\CRUDRelationshipTrait;
use IlBronza\Tasks\Http\Controllers\Tasks\TaskCRUD;
use Illuminate\Http\Request;

class ProjectController extends TaskCRUD
{
    use CRUDCreateStoreTrait;
    use CRUDEditUpdateTrait;
    use CRUDIndexTrait;
    use CRUDPlainIndexTrait;
    use CRUDRelationshipTrait;

    public $configModelClassName = 'project';
    public $allowedMethods = [
        'index',
        'create',
        'store',
        'edit',
        'update',
    ];

    public function getRouteBaseNamePieces()
    {
        return [
            $this->getModelClass()::getStaticRouteBasename(),
        ];
    }

    public function getPackageConfigName(): string
    {
        return 'tasks';
    }

    public function getIndexFieldsArray()
    {
        return config('tasks.models.project.fieldsGroupsFiles.index')::getTracedFieldsGroup();
    }

    public function getIndexElements()
    {
        return $this->getModelClass()::orderBy('name')->get();
    }

    public function getGenericParametersFile(): ?string
    {
        return config('tasks.models.project.parametersFiles.create');
    }

    public function userCanPerformCreate()
    {
        abort_unless($this->getModelClass()::userCanCreate(auth()->user()), 403);
    }

    public function checkUserStoringRights()
    {
        $this->userCanPerformCreate();
    }

    public function edit(string $project)
    {
        return $this->_edit($this->getModelClass()::findOrFail($project));
    }

    public function update(Request $request, string $project)
    {
        return $this->_update($request, $this->getModelClass()::findOrFail($project));
    }
}
