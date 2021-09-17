#!/bin/bash

#dailypaper.php can be run with the arguments:
# --pw
# --cpusa
# --granma

php dailypaper.php --pw > ./paper.tex #run the dailypaper script to retrieve from People's World and dump it into a .tex file
pdflatex -interaction=nonstopmode ./paper.tex #compile the resulting LaTeX file into a pdf
liesel -i ./paper.pdf -o ./readytoprint.pdf #run that pdf through Liesel for home pamphlet-style printing
