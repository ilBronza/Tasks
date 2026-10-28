<?php

namespace IlBronza\Tasks\Http\Controllers\Tasks;

class ClosedTaskIndexController extends TaskIndexController
{
	protected bool $showClosed = true;

	public function getIndexElements()
	{
		return $this->getModelClass()::closed()->latest('updated_at')->get();
	}

}
