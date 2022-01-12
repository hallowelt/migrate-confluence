<?php

namespace HalloWelt\MigrateConfluence\Converter\Preprocessor;

use HalloWelt\MigrateConfluence\Converter\IPreprocessor;

class HtmlTranslation implements IPreprocessor {
	/**
	 * @inheritDoc
	 */
	public function preprocess( string $confluenceHTML ): string {
		/**
		 * As this is a mixture of XML and HTML the XMLParser has trouble
		 * with entities from HTML. To circumvent this we replace all entites
		 * by their literal. A better solution would be to make the entities
		 * known to the XMLParser, e.g. by using a DTD.
		 * This is something for a future development...
		 */
		$aReplaces = array_flip( get_html_translation_table( HTML_ENTITIES ) );
		unset( $aReplaces['&amp;'] );
		unset( $aReplaces['&lt;'] );
		unset( $aReplaces['&gt;'] );
		unset( $aReplaces['&quot;'] );
		foreach ( $aReplaces as $sEntity => $replacement ) {
			$confluenceHTML = str_replace( $sEntity, $replacement, $confluenceHTML );
		}
		return $confluenceHTML;
	}
}
