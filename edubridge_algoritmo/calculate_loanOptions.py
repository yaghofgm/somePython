import numpy as np
import matplotlib.pyplot as plt
import pandas as pd
from ipywidgets import interact, FloatSlider, IntSlider, Dropdown
from IPython.display import display

import warnings
warnings.filterwarnings("ignore")  # só pra não poluir o output com mensagens do matplotlib

#emprestimo = total 4 anos de curso - beca
def test1(emprestimo, juros, GPA, sex, university, course):
    """
    Will see if the person can pay the loan in 10 years without paying more than 1.80x the initial lone

    DISCLAIMER: 
    1. We are assuming a fixed interest rate for the entire period.
    2. We are assuming a fixed monthly payment of 15% of the salary.
    
    Returns: 
    bool: True if the loan is paid according to restrictions, False otherwise
    int: months left to pay the loan
    float: expected return (total amount paid)
    float: remaining debt at the end of the period
    """
    def salary_vector_prediction(GPA, sex, university, course):
        """
        Predicts a salary vector over 10 years (120 months) with stepwise (non-continuous) growth.
        The salary increases once per year and stays fixed for 12 months.
        
        DISCLAIMERS: 
        1. The growth rate should also depend on the course and on the university, 
        but for now we will assume a fixed growth rate of 10% per year.
        2. These are based on highschool GPA, not on university GPA. Will need another dataset for the other one. 

        Parameters:
        base_salary (float): The current salary.
        
        Returns:
        float: The predicted salary_vector long 10 years.

        """
        
        def base_salary(GPA, sex, university, course):
            salary_data = {
                "Tecnologico de Monterrey Estado de Mexico": {
                    "Ingenieria Mecanica": 10000,
                    "Ingeniería Quimica": 12500,
                    "Ingeniería Computacion y Tecnologias de la informacion": 15000,
                },
                "Anahuac": {
                    "Ingenieria Industrial": 10000,
                    "Ingenieria Mecanica": 10000,
                },
                "University of Michigan Ann Arbor": {
                    "Electrical & Computer Engineering": 77300,
                    "Mechanical Engineering": 89668,
                }
            }

            gpa_data = {
                "Tecnologico de Monterrey Estado de Mexico": {
                    "Ingenieria Mecanica": 3.5,
                    "Ingeniería Quimica": 3.6,
                    "Ingeniería Computacion y Tecnologias de la informacion": 3.7,
                },
                "Anahuac": {
                    "Ingenieria Industrial": 3.5,
                    "Ingenieria Mecanica": 3.4,
                },
                "University of Michigan Ann Arbor": {
                    "Electrical & Computer Engineering": 3.7,
                    "Mechanical Engineering": 3.6,
                }
            }

            raw_salary = salary_data.get(university, {}).get(course, 50000) #default salary is 50000
            gpa_avg = gpa_data.get(university, {}).get(course, 3.5) #default GPA is 3.5

            if sex == "male":
                beta = 0.1185
            elif sex == "female":
                beta = 0.1377
            else:
                raise ValueError("Invalid value for 'sex'. It must be either 'male' or 'female'.")

            return raw_salary * np.exp(beta * (GPA - gpa_avg)) #default default will be 50,000*e^(beta*(GPA-3.5))

        def get_annual_growth(university, course):
            growth_data = {
                "University A": {
                    "Engineering": 0.10,
                    "Arts": 0.05,
                },
                "University B": {
                    "Engineering": 0.12,
                    "Arts": 0.07,
                },
            }
            return growth_data.get(university, {}).get(course, 0.1)

        # --- Core logic ---
        initial_salary = base_salary(GPA, sex, university, course)
        annual_growth = get_annual_growth(university, course)

        salary_vector = []
        current_salary = initial_salary

        for month in range(120):
            salary_vector.append(current_salary)
            if (month + 1) % 12 == 0:  # increase salary once per year
                current_salary *= (1 + annual_growth)

        return salary_vector
    
    salary_vec = salary_vector_prediction(GPA, sex, university, course)

    def update_divida(months,divida,salary_vec,juros):
        monthly_payment = salary_vec[months] * 0.15  # e.g., 15% of income
        payment = min(monthly_payment, divida)
        divida = (divida - payment) * (1 + juros)

        return max(divida, 0)  

    # months=np.arange(120)
    divida_list = []
    total_pago_list=[]
    divida = emprestimo
    total_pago=0
    months=0
    
    while divida > 0 and months < 120 and total_pago < emprestimo*1.8:
        divida = update_divida(months,divida,salary_vec,juros)
        divida_list.append(divida)
        total_pago_list.append(total_pago)
        total_pago += salary_vec[months] * 0.15  # CORREÇÃO AQUI
        months += 1


    # plt.figure(figsize=(8,4))
    # plt.plot(range(len(divida_list)), divida_list, label="Remaining Debt")
    # plt.plot(range(len(total_pago_list)), total_pago_list, 'r--', label="Total Paid (accumulated)")
    # plt.xlabel("Months")
    # plt.ylabel("Amount (R$)")
    # plt.title("Loan Evolution Over Time")
    # plt.grid(True)
    # plt.legend()
    # plt.show()

    was_paid = divida_list[-1] == 0
    months_left = max(120-months, 0)  # CORREÇÃO AQUI
    expected_return = total_pago

    # if was_paid:
    #     print(f"Divida quitada em {months} meses")
    # elif months == 120:
    #     print(f"Divida não quitada em 120 meses. Divida restante: {divida_list[-1]}")
    # else:
    #     print(f"Excedeu 1.8x empréstimo. Divida restante: {divida_list[-1]}")

    # return was_paid, months_left, expected_return
    return {
        "was_paid": was_paid,
        "months_left": months_left,
        "expected_return": expected_return,
        "remaining_debt": divida_list[-1]
    }
def test2(threshold, emprestimo, expected_return, country="BR", risk1=None, risk2=None, risk3=None, risk4=None):
    """
    Check if the expected value is above the profit threshold 
    Expected Value = (1-risk) * (expected return) + (risk) *(-emprestimo)
    
    check Expected Value > threshold * emprestimo
    
    Parameters:
    threshold (float): The threshold for risk worthiness.
    emprestimo (float): The loan amount.
    expected_return (float): The expected return.
    
    Returns:
    dict: A dictionary containing the results of the risk analysis.
    """
    def risk(country, risk1=None, risk2=None, risk3=None, risk4=None):
        """
        Calculate the risk based on the country and other factors
        
        Parameters:
        emprestimo (float): The loan amount.
        expected_return (float): The expected return.
        
        Returns:
        float: The calculated risk.
        """
        # Default country-level risk estimates
        base_risk = {
            "BR": 0.35,
            "US": 0.12,
            "MX": 0.20,
        }
        return base_risk.get(country.upper(), 0.25)  # fallback: 25%

    risco = risk(country, risk1=None, risk2=None, risk3=None, risk4=None)
    expected_value = (1 - risco) * (expected_return) + risco * (-emprestimo)
    return {
        "worthy": expected_value > threshold * emprestimo,
        "expected_value profit": expected_value - emprestimo,
        "profit percentage": (expected_value - emprestimo) / emprestimo,
        "risk": risco
    }
def test12(**kwargs):
    """
    Wrapper function to run both test1 and test2 and return their results.

    Parameters:
    kwargs: Arguments for test1 and test2.

    Returns:
    dict: A dictionary containing the results of test1 and test2.
        result1,result2:
            result1:{
                "was_paid": bool,
                "months_left": int,
                "expected_return": float,
                "remaining_debt": float
            }
            result2:{
                "worthy": bool,
                "expected_value profit": float,
                "profit percentage": float,
                "risk": float
            }
    """
    # Run test1
    result1 = test1(
        emprestimo=kwargs["emprestimo"],
        juros=kwargs["juros"],
        GPA=kwargs["GPA"],
        sex=kwargs["sex"],
        university=kwargs["university"],
        course=kwargs["course"]
    )

    # Run test2
    result2 = test2(
        threshold=kwargs["profit_threshold"],
        emprestimo=kwargs["emprestimo"],
        expected_return=result1["expected_return"],
        country=kwargs["country"]
    )

    return result1, result2

def getRange2Offer(**kwargs):
    """
    Get the range of values for the loan offer for a given interest value 
    """
    loan_range = []
    interest_rates = np.arange(0.01, 0.101, 0.01)

    for juros in interest_rates:
        row = []  # Valores de empréstimo válidos para esta taxa de juros
        for loan in range(5000, 305000, 5000):
            kwargs["juros"] = juros
            kwargs["emprestimo"] = loan
            result1, result2 = test12(**kwargs)
            if result2["worthy"] and result1["was_paid"]:
                row.append(loan)
        loan_range.append(row)
    
    return interest_rates, loan_range
def detectar_subintervalos(lista):
    if not lista:
        return []

    subintervalos = []
    inicio = lista[0]
    fim = lista[0]

    for valor in lista[1:]:
        if valor == fim + 5000:
            fim = valor
        else:
            subintervalos.append(f"[{inicio}, {fim}]")
            inicio = fim = valor
    subintervalos.append(f"[{inicio}, {fim}]")
    return subintervalos
def interactive_range_display(
    GPA=3.5,
    sex="male",
    university="University A",
    course="Engineering",
    profit_threshold=1.1,
    country="MX"
):
    interest_rates, loan_range = getRange2Offer(
        GPA=GPA,
        sex=sex,
        university=university,
        course=course,
        profit_threshold=profit_threshold,
        country=country
    )

    # Montar dados de subintervalos para cada taxa
    intervalos_formatados = []
    for row in loan_range:
        partes = detectar_subintervalos(row)
        if partes:
            intervalos_formatados.append(", ".join(partes))
        else:
            intervalos_formatados.append("Nenhum")

    df = pd.DataFrame({
        "Taxa de Juros (%)": (interest_rates * 100).round(2),
        "Intervalos de Empréstimos Válidos (R$)": intervalos_formatados
    })

    df.reset_index(drop=True, inplace=True)
    with pd.option_context('display.max_colwidth', None):
        display(df)

# Interface interativa (sem sliders de empréstimo e juros)
interact(
    interactive_range_display,
    GPA=FloatSlider(min=2.0, max=4.0, step=0.1, value=3.5, description="GPA"),
    profit_threshold=FloatSlider(min=1.0, max=2.0, step=0.01, value=1.1, description="Lucro Mínimo"),
    university=Dropdown(options=["University A", "University B"], value="University A", description="Universidade"),
    course=Dropdown(options=["Engineering", "Arts"], value="Engineering", description="Curso"),
    country=Dropdown(options=["BR", "US", "MX"], value="MX", description="País"),
    sex=Dropdown(options=["male", "female"], value="male", description="Sexo")
) 
