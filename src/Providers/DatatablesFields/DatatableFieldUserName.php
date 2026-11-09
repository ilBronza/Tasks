<?php

namespace IlBronza\Tasks\Providers\DatatablesFields;

use IlBronza\Datatables\DatatablesFields\Users\DatatableFieldName;

class DatatableFieldUserName extends DatatableFieldName
{
	public function transformValue($value)
	{
		return e(parent::transformValue($value));
	}
}
