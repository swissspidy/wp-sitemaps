<?php
/**
 * Sitemaps: Core_Sitemaps_Stylesheet class
 *
 * This class provides the XSL stylesheets to style all sitemaps.
 *
 * @package WordPress
 * @subpackage Sitemaps
 * @since x.x.x
 */

/**
 * Stylesheet provider class.
 */
class Core_Sitemaps_Stylesheet {
	/**
	 * Renders the xsl stylesheet depending on whether its the sitemap index or not.
	 */
	public function render_stylesheet() {
		$stylesheet_query = get_query_var( 'sitemap-stylesheet' );

		if ( ! empty( $stylesheet_query ) ) {
			header( 'Content-type: application/xml; charset=UTF-8' );

			if ( 'xsl' === $stylesheet_query ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- All content escaped below.
				echo $this->get_sitemap_stylesheet();
			}

			if ( 'index' === $stylesheet_query ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- All content escaped below.
				echo $this->get_sitemap_index_stylesheet();
			}

			exit;
		}
	}

	/**
	 * Returns the escaped xsl for all sitemaps, except index.
	 */
	public function get_sitemap_stylesheet() {
		$css         = $this->get_stylesheet_css();
		$title       = esc_html__( 'XML Sitemap', 'core-sitemaps' );
		$description = sprintf(
			/* translators: %s: URL to sitemaps documentation. */
			__( 'This XML Sitemap is generated by WordPress to make your content more visible for search engines. Learn more about XML sitemaps on <a href="%s">sitemaps.org</a>.', 'core-sitemaps' ),
			__( 'https://www.sitemaps.org/', 'core-sitemaps' )
		);
		$text        = sprintf(
			/* translators: %s: number of URLs. */
			__( 'This XML Sitemap contains %s URLs.', 'core-sitemaps' ),
			'<xsl:value-of select="count(sitemap:urlset/sitemap:url)"/>'
		);

		$url           = esc_html__( 'URL', 'core-sitemaps' );
		$last_modified = esc_html__( 'Last Modified', 'core-sitemaps' );

		$xsl_content = <<<XSL
<?xml version="1.0" encoding="UTF-8"?>
			<xsl:stylesheet version="2.0"
				xmlns:html="http://www.w3.org/TR/REC-html40"
				xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"
				xmlns:sitemap="http://www.sitemaps.org/schemas/sitemap/0.9"
				xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
			<xsl:output method="html" version="1.0" encoding="UTF-8" indent="yes"/>
			<xsl:template match="/">
				<html xmlns="http://www.w3.org/1999/xhtml">
				<head>
					<title>$title</title>
					<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
					<style type="text/css">
						$css
					</style>
				</head>
				<body>
					<div id="sitemap__header">
						<h1>$title</h1>
						<p>$description</p>
					</div>
					<div id="sitemap__content">
						<p class="text">$text</p>
						<table id="sitemap__table">
							<thead>
							<tr>
								<th>$url</th>
								<th>$last_modified</th>
							</tr>
							</thead>
							<tbody>
							<xsl:for-each select="sitemap:urlset/sitemap:url">
								<tr>
									<td>
										<xsl:variable name="itemURL">
											<xsl:value-of select="sitemap:loc"/>
										</xsl:variable>
										<a href="{\$itemURL}">
											<xsl:value-of select="sitemap:loc"/>
										</a>
									</td>
								</tr>
							</xsl:for-each>
							</tbody>
						</table>

					</div>
				</body>
				</html>
			</xsl:template>
			</xsl:stylesheet>\n
XSL;

		/**
		 * Filter the content of the sitemap stylesheet.
		 *
		 * @param string $xsl Full content for the xml stylesheet.
		 */
		return apply_filters( 'core_sitemaps_stylesheet_content', $xsl_content );
	}

	/**
	 * Returns the escaped xsl for the index sitemaps.
	 */
	public function get_sitemap_index_stylesheet() {
		$css         = $this->get_stylesheet_css();
		$title       = esc_html__( 'XML Sitemap', 'core-sitemaps' );
		$description = sprintf(
			/* translators: %s: URL to sitemaps documentation. */
			__( 'This XML Sitemap is generated by WordPress to make your content more visible for search engines. Learn more about XML sitemaps on <a href="%s">sitemaps.org</a>.', 'core-sitemaps' ),
			__( 'https://www.sitemaps.org/', 'core-sitemaps' )
		);
		$text        = sprintf(
			/* translators: %s: number of URLs. */
			__( 'This XML Sitemap contains %s URLs.', 'core-sitemaps' ),
			'<xsl:value-of select="count(sitemap:sitemapindex/sitemap:sitemap)"/>'
		);

		$url           = esc_html__( 'URL', 'core-sitemaps' );
		$last_modified = esc_html__( 'Last Modified', 'core-sitemaps' );

		$xsl_content = <<<XSL
<?xml version="1.0" encoding="UTF-8"?>
			<xsl:stylesheet version="2.0"
				xmlns:html="http://www.w3.org/TR/REC-html40"
				xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"
				xmlns:sitemap="http://www.sitemaps.org/schemas/sitemap/0.9"
				xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
			<xsl:output method="html" version="1.0" encoding="UTF-8" indent="yes"/>
			<xsl:template match="/">
				<html xmlns="http://www.w3.org/1999/xhtml">
				<head>
					<title>$title</title>
					<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
					<style type="text/css">
						$css
					</style>
				</head>
				<body>
					<div id="sitemap__header">
						<h1>$title</h1>
						<p>$description</p>
					</div>
					<div id="sitemap__content">
						<p class="text">$text</p>
						<table id="sitemap__table">
							<thead>
							<tr>
								<th>$url</th>
								<th>$last_modified</th>
							</tr>
							</thead>
							<tbody>
							<xsl:for-each select="sitemap:sitemapindex/sitemap:sitemap">
								<tr>
									<td>
										<xsl:variable name="itemURL">
											<xsl:value-of select="sitemap:loc"/>
										</xsl:variable>
										<a href="{\$itemURL}">
											<xsl:value-of select="sitemap:loc"/>
										</a>
									</td>
								</tr>
							</xsl:for-each>
							</tbody>
						</table>

					</div>
				</body>
				</html>
			</xsl:template>
			</xsl:stylesheet>\n
XSL;

		/**
		 * Filter the content of the sitemap index stylesheet.
		 *
		 * @param string $xsl Full content for the xml stylesheet.
		 */
		return apply_filters( 'core_sitemaps_index_stylesheet_content', $xsl_content );
	}

	/**
	 * The CSS to be included in sitemap XSL stylesheets.
	 *
	 * @return string The CSS.
	 */
	protected function get_stylesheet_css() {
		$css = '
			body {
				font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
				color: #444;
			}

			#sitemap__table {
				border: solid 1px #ccc;
				border-collapse: collapse;
			}

			#sitemap__table tr th {
				text-align: left;
			}

			#sitemap__table tr td,
			#sitemap__table tr th {
				padding: 10px;
			}

			#sitemap__table tr:nth-child(odd) td {
				background-color: #eee;
			}

			a:hover {
				text-decoration: none;
			}';

		/**
		 * Filter the css only for the sitemap stylesheet.
		 *
		 * @param string $css CSS to be applied to default xsl file.
		 */
		return apply_filters( 'core_sitemaps_stylesheet_css', $css );
	}
}
