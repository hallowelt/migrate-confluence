<?php

namespace HalloWelt\MigrateConfluence\Composer\Processor;

class TemplatePages extends Pages {

	/**
	 * @inheritDoc
	 */
	protected function postProcessContent( string $pageTitle, string $pageContent ): string {
		if ( strpos( $pageTitle, 'Template:' ) !== 0 ) {
			return $pageContent;
		}
		// Skip default (non-migrated) templates
		$defaultTemplates = $this->getDefaultTemplateNames();
		if ( in_array( $pageTitle, $defaultTemplates ) ) {
			return $pageContent;
		}

		return $pageContent . "\n<includeonly>{{#set: Created by macro=$1 }}</includeonly>\n";
	}

	/**
	 * @return array
	 */
	private function getDefaultTemplateNames(): array {
		$basepath = dirname( __DIR__ ) . '/_defaultpages/Template/';
		$names = [];
		if ( is_dir( $basepath ) ) {
			foreach ( scandir( $basepath ) as $file ) {
				if ( $file === '.' || $file === '..' ) {
					continue;
				}
				$names[] = 'Template:' . $file;
			}
		}
		return $names;
	}
}
