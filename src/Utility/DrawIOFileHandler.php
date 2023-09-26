<?php

namespace HalloWelt\MigrateConfluence\Utility;

class DrawIOFileHandler {

	/**
	 * Checks by file extension if that's associated with DrawIO temporary file.
	 * That could be either ".drawio" file or ".drawio.tmp" file
	 *
	 * @param string $fileName
	 * @return bool
	 */
	public function isDrawIODataFile( string $fileName ): bool {
		return (
			preg_match( '#\.drawio.tmp$#', $fileName ) ||
			preg_match( '#\.drawio$#', $fileName )
		);
	}

	/**
	 * Checks by file extension if that's associated with DrawIO PNG image
	 *
	 * @param string $fileName
	 * @return bool
	 */
	public function isDrawIOImage( string $fileName ): bool {
		return preg_match( '#\.drawio.png$#', $fileName );
	}

	/**
	 * Checks by file content if that's associated with XML of DrawIO diagram
	 *
	 * @param string $fileContent
	 * @return bool
	 */
	public function isDrawIODataContent( string $fileContent ): bool {
		return preg_match( '#<mxfile.*?>\s*.*\s*<diagram.*?>#', $fileContent );
	}

	/**
	 * Encodes and bakes DrawIO diagram XML into PNG image "tEXt" data chunk
	 *
	 * @param string $imageContent
	 * @param string $diagramXml
	 * @return string
	 */
	public function bakeDiagramDataIntoImage( string $imageContent, string $diagramXml ): string {
		// "urlencode" does not suit us here because it encodes spaces with "+" symbol
		// That breaks diagram data processing by DrawIO editor
		// We need to encode spaces as "%20", so "rawurlencode" is used
		// See https://www.php.net/manual/en/function.urlencode.php
		$diagramXmlEncoded = rawurlencode( $diagramXml );

		$keyword = 'mxfile';

		$chunkData = $keyword . "\0" . $diagramXmlEncoded;

		$crc = pack( 'N', crc32( 'tEXt' . $chunkData ) );

		// Create the tEXt chunk
		$tEXtChunk = pack( 'N', strlen( $chunkData ) ) . 'tEXt' . $chunkData . $crc;

		$IDATChunkPos = strpos( $imageContent, 'IDAT', 8 );

		// Add the tEXt chunk to the image content
		$imageContent = substr_replace( $imageContent, $tEXtChunk, $IDATChunkPos - 4, 0 );

		return $imageContent;
	}
}
