# About
This module serves a singular endpoint `/hocr_annotation/search` which is meant for the SimpleAnnotationV2 Adapter to recognize within the mirador annotation plugin.
This module is simply a prototype to test the limitations of the mirador annotation plugin.

# How to get it working
Note that this is implemented in a way that it would only work under the isle-dc stack from `digitalutsc` under the [hOCR branch](https://github.com/digitalutsc/isle-dc/tree/hocr).

Note you would also need to repack the mirador library together with the mirador annotation plugin for this to work. [Details here](https://github.com/digitalutsc/mirador_pack)

Please apply the `Patch/annotation_mirador.patch` file to the islandora_mirador module to add the annotation API endpoints for proper functionality.

# How it works
The logic is still very unoptimized but here is the general flow
 - When a request arrives, it will attempt to find the specific node that is the owner of the canvas (media)
 - Once this node is found, another search for its hOCR media is performed
 - Once the hOCR media is retrieved, we attempt to get its file content
 - For each ocrx_word in the file content, we attempt to represented it in an annotation list format for the adapter
 - The final list is returned as a representation and could be loaded by the annotation mirador plugin