<?php

namespace App\Http\Controllers;

use App\Exceptions\ConfigurationException;
use App\Exceptions\Internal\QueryBuilderException;
use App\Http\Requests\Legacy\TranslateIDRequest;
use App\Legacy\Legacy;
use Illuminate\Routing\Controller;

/**
 * Class LegacyController.
 *
 * API calls which should not exist. ;-)
 */
class LegacyController extends Controller
{
	/**
	 * Translates IDs from legacy to modern format.
	 *
	 * @param TranslateIDRequest $request the request
	 *
	 * @return array{albumID?: string, photoID?: string} the modern IDs
	 *
	 * @throws ConfigurationException thrown, if translation is disabled by
	 *                                configuration
	 * @throws QueryBuilderException  thrown by the ORM layer in case of an
	 *                                error
	 */
	public function translateLegacyModelIDs(TranslateIDRequest $request): array
	{
		$legacyAlbumID = $request->albumID();
		$legacyPhotoID = $request->photoID();

		$return = [];
		if ($legacyAlbumID !== null) {
			$return['albumID'] = Legacy::isLegacyModelID($legacyAlbumID) ?
				Legacy::translateLegacyAlbumID($request->albumID(), $request) :
				null;
		}
		if ($legacyPhotoID !== null) {
			$return['photoID'] = Legacy::isLegacyModelID($legacyPhotoID) ?
				Legacy::translateLegacyPhotoID($legacyPhotoID, $request) :
				null;
		}

		return $return;
	}
}