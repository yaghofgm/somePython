import numpy as np
import matplotlib.pyplot as plt
from ipywidgets import interact, FloatSlider, IntSlider

import warnings
warnings.filterwarnings("ignore")  # só pra não poluir o output com mensagens do matplotlib

# while(montante>0,months<120,montante<emprestimo*1.8):
# burro, era para usar and, senao vodce fe uma tupla que roda enquanto a tupla for verdadeira

def update_plot(emprestimo=100000, juros=0.01, salary=500):
    def update_montante(montante,salary,juros):
        montante = (montante-salary)*(1+juros)  
        if montante <0:
            montante = 0
        return montante

    # months=np.arange(120)
    montante_list = []
    montante = emprestimo
    months=0
    
    while montante > 0 and months < 120 and montante < emprestimo*1.8:
        montante = update_montante(montante,salary,juros)
        montante_list.append(montante)
        months += 1

    plt.figure(figsize=(8,4))
    plt.plot(range(len(montante_list)), montante_list)
    plt.xlabel("Meses")
    plt.ylabel("Montante Devido")
    plt.title("Evolução do Montante ao Longo do Tempo")
    plt.grid(True)
    plt.show()

    print(f"total de meses até quitar ou atingir 1.8x o valor do empréstimo: {months}")

# update_plot()
# Sliders
interact(
    update_plot,
    emprestimo=IntSlider(min=1000, max=200000, step=1000, value=100000, description="Empréstimo"),
    salary=IntSlider(min=100, max=5000, step=100, value=5000, description="Salário"),
    juros=FloatSlider(min=0.001, max=0.05, step=0.001, value=0.01, description="Juros")
)